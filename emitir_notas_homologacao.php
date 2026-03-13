<?php
require __DIR__ . '/config/db.php';

$mysqli = db_connect();

// Config + classes NFS-e
$config = require __DIR__ . '/config_nfse_escola.php';
require_once __DIR__ . '/classes/DPSBuilder.php';
require_once __DIR__ . '/classes/XMLSigner.php';
require_once __DIR__ . '/classes/GZipHelper.php';
require_once __DIR__ . '/classes/HTTPClient.php';

// Se existir cadastro da escola, sobrepõe dados de prestador/serviço/senha
$empresa = null;
try {
    $res = $mysqli->query('SELECT * FROM empresa LIMIT 1');
    if ($res) {
        $empresa = $res->fetch_assoc();
        $res->close();
    }
} catch (Throwable $e) {
    $empresa = null;
}

if ($empresa) {
    $config['prestador']['cnpj']               = preg_replace('/\D/', '', $empresa['cnpj']);
    $config['prestador']['inscricao_municipal'] = $empresa['inscricao_municipal'] ?? '';
    $config['prestador']['razao_social']        = $empresa['razao_social'];
    $config['prestador']['nome_fantasia']       = $empresa['nome_fantasia'] ?: $empresa['razao_social'];
    $config['prestador']['logradouro']          = $empresa['logradouro'];
    $config['prestador']['numero']              = $empresa['numero'];
    $config['prestador']['complemento']         = $empresa['complemento'] ?? '';
    $config['prestador']['bairro']              = $empresa['bairro'];
    $config['prestador']['codigo_municipio']    = $empresa['codigo_municipio_ibge'];
    $config['prestador']['uf']                  = $empresa['uf'];
    $config['prestador']['cep']                 = $empresa['cep'];
    $config['prestador']['telefone']            = $empresa['telefone'] ?? '';
    $config['prestador']['email']               = $empresa['email_financeiro'] ?? '';

    $config['servico']['item_lista_servico']          = $empresa['item_lista_servico'];
    $config['servico']['codigo_tributacao_municipal'] = $empresa['codigo_tributacao_municipal'];
    // cTribNac deve conter apenas dígitos (ex.: 080101), sem pontos
    $config['servico']['codigo_tributacao_nacional']  = preg_replace('/\D/', '', $empresa['codigo_tributacao_nacional']);
    $config['servico']['codigo_nbs']                  = $empresa['codigo_nbs'] ?: $config['servico']['codigo_nbs'];
    $config['servico']['aliquota_iss']                = (float)$empresa['aliquota_iss'];
    if (!empty($empresa['descricao_servico_padrao'])) {
        $config['servico']['descricao_padrao'] = $empresa['descricao_servico_padrao'];
    }

    if (!empty($empresa['certificado_senha'])) {
        $config['nacional']['certificado_senha'] = $empresa['certificado_senha'];
    }
}

$errors = [];
$success = null;
$resumo = [
    'sucesso' => 0,
    'erro'    => 0,
];
$detalhesNotas = [];

/**
 * Extrai número e (se houver) código de verificação da NFS-e retornada.
 */
function extrairDadosNFSeSimples(string $nfse_xml): array
{
    $dom = new DOMDocument();
    $dom->loadXML($nfse_xml);

    $numero = null;
    $cod   = null;

    $nNodes = $dom->getElementsByTagName('nNFSe');
    if ($nNodes->length > 0) {
        $numero = $nNodes->item(0)->nodeValue;
    }

    $cNodes = $dom->getElementsByTagName('codigoVerificacao');
    if ($cNodes->length > 0) {
        $cod = $cNodes->item(0)->nodeValue;
    }

    if (!$cod) {
        $cNodes = $dom->getElementsByTagName('cVerif');
        if ($cNodes->length > 0) {
            $cod = $cNodes->item(0)->nodeValue;
        }
    }

    return [
        'numero' => $numero,
        'cod'    => $cod,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = $_POST['notas'] ?? [];
    $idsFiltrados = [];
    foreach ($ids as $id) {
        $idInt = (int)$id;
        if ($idInt > 0) {
            $idsFiltrados[] = $idInt;
        }
    }

    if (empty($idsFiltrados)) {
        $errors[] = 'Selecione ao menos uma nota para emissão em homologação.';
    } elseif (!$empresa) {
        $errors[] = 'Cadastre primeiro os dados da escola na tela "Escola".';
    } else {
        // Busca notas + clientes
        $placeholders = implode(',', array_fill(0, count($idsFiltrados), '?'));
        $tipos        = str_repeat('i', count($idsFiltrados));

        $sql = "SELECT n.id, n.competencia, n.valor_servico, n.descricao_servico,
                       c.nome_tomador, c.nome_aluno, c.cpf_cnpj, c.email,
                       c.logradouro, c.numero, c.bairro, c.cidade, c.uf, c.cep, c.telefone
                FROM notas n
                JOIN clientes c ON c.id = n.cliente_id
                WHERE n.id IN ($placeholders) AND n.status = 'PENDENTE'";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param($tipos, ...$idsFiltrados);
        $stmt->execute();
        $resNotas = $stmt->get_result();
        $notasSel = $resNotas->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($notasSel)) {
            $errors[] = 'Nenhuma nota pendente encontrada para os IDs selecionados.';
        } else {
            $httpClient = new HTTPClient($config);

            foreach ($notasSel as $nota) {
                $idNota = (int)$nota['id'];

                $cpfCnpj = preg_replace('/\D/', '', $nota['cpf_cnpj']);
                $fisJur  = strlen($cpfCnpj) === 14 ? 'J' : 'F';

                $nomeTomador = $nota['nome_tomador'];
                if (!empty($nota['nome_aluno'])) {
                    $nomeTomador .= ' - ' . $nota['nome_aluno'];
                }

                $ddd  = '0';
                $fone = preg_replace('/\D/', '', $nota['telefone'] ?? '');
                if (strlen($fone) >= 10) {
                    $ddd  = substr($fone, 0, 2);
                    $fone = substr($fone, 2);
                }

                $dadosFatura = [
                    'valor_servicos'   => (float)$nota['valor_servico'],
                    'valor_descontos'  => 0.0,
                    'valor_deducoes'   => 0.0,
                    'cnpj_cpf'         => $cpfCnpj,
                    'fis_jur'          => $fisJur,
                    'raz_social'       => $nomeTomador,
                    'endereco'         => $nota['logradouro'],
                    'numero'           => $nota['numero'],
                    'bairro'           => $nota['bairro'],
                    'codigo_municipio' => $empresa['codigo_municipio_ibge'],
                    'estado'           => $nota['uf'],
                    'cep'              => $nota['cep'],
                    'ddd'              => $ddd,
                    'telefone'         => $fone,
                    'email'            => $nota['email'],
                    'descricao'        => $nota['descricao_servico'],
                    'numero_dps'       => $idNota,
                ];

                try {
                    // Monta e assina DPS
                    $builder  = new DPSBuilder($config, $dadosFatura);
                    $xmlDps   = $builder->montarDPS();
                    $signer   = new XMLSigner(
                        $config['nacional']['certificado_path'],
                        $config['nacional']['certificado_senha']
                    );
                    $xmlAss   = $signer->assinarXML($xmlDps, 'infDPS');
                    $dpsGzB64 = GZipHelper::compactar($xmlAss);

                    // Envia para API
                    $resApi = $httpClient->enviarDPS($dpsGzB64);

                    $httpCode = $resApi['_http_code'] ?? 0;
                    if ($httpCode !== 201) {
                        // Erro retornado pela API
                        $msgErro = 'HTTP ' . $httpCode;
                        $rawJson = json_encode($resApi, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        if (!empty($resApi['erros']) && is_array($resApi['erros'])) {
                            $parts = [];
                            foreach ($resApi['erros'] as $err) {
                                $c = $err['Codigo'] ?? $err['codigo'] ?? '';
                                $d = $err['Descricao'] ?? $err['descricao'] ?? '';
                                $parts[] = trim($c . ' ' . $d);
                            }
                            if ($parts) {
                                $msgErro .= ' - ' . implode(' | ', $parts);
                            }
                        }

                        $upd = $mysqli->prepare(
                            "UPDATE notas
                             SET status = 'ERRO', mensagem_erro = ?
                             WHERE id = ?"
                        );
                        $upd->bind_param('si', $msgErro, $idNota);
                        $upd->execute();
                        $upd->close();

                        $resumo['erro']++;
                        $detalhesNotas[] = [
                            'id'    => $idNota,
                            'ok'    => false,
                            'mensagem' => $msgErro,
                            'raw'      => $rawJson,
                        ];
                        continue;
                    }

                    $chave = $resApi['chaveAcesso'] ?? null;
                    $gzXml = $resApi['nfseXmlGZipB64'] ?? null;

                    $numeroNfse = null;
                    $codVerif   = null;

                    if ($gzXml) {
                        $nfseXml   = GZipHelper::descompactar($gzXml);
                        $dadosNfse = extrairDadosNFSeSimples($nfseXml);
                        $numeroNfse = $dadosNfse['numero'];
                        $codVerif   = $dadosNfse['cod'];
                    }

                    $upd = $mysqli->prepare(
                        "UPDATE notas
                         SET status = 'EMITIDA',
                             mensagem_erro = NULL,
                             numero_nfse = ?,
                             codigo_verificacao = ?,
                             chave_acesso = ?,
                             data_emissao = NOW()
                         WHERE id = ?"
                    );
                    $upd->bind_param(
                        'sssi',
                        $numeroNfse,
                        $codVerif,
                        $chave,
                        $idNota
                    );
                    $upd->execute();
                    $upd->close();

                    $resumo['sucesso']++;
                    $detalhesNotas[] = [
                        'id'     => $idNota,
                        'ok'     => true,
                        'numero' => $numeroNfse,
                        'chave'  => $chave,
                    ];
                } catch (Throwable $e) {
                    $msgErro = $e->getMessage();
                    $upd = $mysqli->prepare(
                        "UPDATE notas
                         SET status = 'ERRO', mensagem_erro = ?
                         WHERE id = ?"
                    );
                    $upd->bind_param('si', $msgErro, $idNota);
                    $upd->execute();
                    $upd->close();

                    $resumo['erro']++;
                    $detalhesNotas[] = [
                        'id'    => $idNota,
                        'ok'    => false,
                        'mensagem' => $msgErro,
                        'raw'      => null,
                    ];
                }
            }

            $success = 'Processo de emissão em homologação concluído para as notas selecionadas.';
        }
    }
}

// Carrega notas pendentes atualizadas (depois de eventual envio)
$notasPendentes = [];
try {
    $sql = "SELECT n.id, n.competencia, n.valor_servico, n.status,
                   c.nome_aluno, c.nome_tomador, c.cpf_cnpj
            FROM notas n
            JOIN clientes c ON c.id = n.cliente_id
            WHERE n.status = 'PENDENTE'
            ORDER BY n.competencia, c.nome_tomador";
    $res = $mysqli->query($sql);
    if ($res) {
        $notasPendentes = $res->fetch_all(MYSQLI_ASSOC);
        $res->close();
    }
} catch (Throwable $e) {
    $notasPendentes = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Maple Bear | Emissão em homologação (NFS-e)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="public/styles.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <div class="brand">
            <div class="brand-mark">MB</div>
            <div class="brand-text">
                <span class="brand-title">Maple Bear</span>
                <span class="brand-subtitle">Financeiro · NFSe</span>
            </div>
        </div>
        <nav class="topnav" data-section="notas">
            <div class="topnav-indicator"></div>
            <a href="index.php" class="topnav-item active" data-section-target="notas">Notas fiscais</a>
            <a href="empresa.php" class="topnav-item" data-section-target="escola">Escola</a>
            <a href="clientes.php" class="topnav-item" data-section-target="clientes">Clientes</a>
        </nav>
    </div>
</header>

<main class="page">
    <section class="section">
        <header class="section-header">
            <div>
                <h2 class="section-title">Emissão em homologação (Portal Nacional)</h2>
                <p class="section-description">
                    Envia as notas <strong>pendentes</strong> para o ambiente de testes do Portal Nacional NFS-e,
                    usando o certificado configurado. Os resultados são gravados na própria tabela de notas.
                </p>
            </div>
            <div class="section-actions">
                <a href="index.php" class="btn">Voltar ao painel</a>
            </div>
        </header>

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <strong>Ocorreram erros durante a emissão.</strong>
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <strong><?= htmlspecialchars($success) ?></strong>
                <p>
                    Sucesso: <?= (int)$resumo['sucesso'] ?> |
                    Erros: <?= (int)$resumo['erro'] ?>
                </p>
                <?php if ($detalhesNotas): ?>
                    <ul>
                        <?php foreach ($detalhesNotas as $d): ?>
                            <li>
                                Nota #<?= (int)$d['id'] ?>:
                                <?php if (!empty($d['ok'])): ?>
                                    EMITIDA
                                    <?php if (!empty($d['numero'])): ?>
                                        · NFS-e <?= htmlspecialchars($d['numero']) ?>
                                    <?php endif; ?>
                                    <?php if (!empty($d['chave'])): ?>
                                        · Chave <?= htmlspecialchars($d['chave']) ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    ERRO – <?= htmlspecialchars($d['mensagem'] ?? '') ?>
                                    <?php if (!empty($d['raw'])): ?>
                                        <details style="margin-top:4px;">
                                            <summary style="cursor:pointer;">Ver detalhes técnicos da resposta</summary>
                                            <pre style="background:#f1f5f9;padding:0.5rem;border-radius:4px;overflow-x:auto;font-size:0.8rem;"><?= htmlspecialchars($d['raw']) ?></pre>
                                        </details>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Notas pendentes</h3>
                    <p class="card-description">
                        Selecione as notas que você deseja realmente testar no ambiente de homologação.
                        Esse envio é real para o Portal Nacional (mas não para o ambiente de produção).
                    </p>
                </div>
            </div>
            <div class="card-body">
                <?php if (!$notasPendentes): ?>
                    <p class="empty-state">
                        Não há notas pendentes no momento. Gere notas da competência pelo painel inicial.
                    </p>
                <?php else: ?>
                    <form method="post" class="form">
                        <div class="table-wrapper">
                            <table class="table">
                                <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" onclick="toggleAll(this)">
                                    </th>
                                    <th>ID</th>
                                    <th>Competência</th>
                                    <th>Aluno / Tomador</th>
                                    <th>CPF/CNPJ</th>
                                    <th>Valor (R$)</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($notasPendentes as $n): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox"
                                                   name="notas[]"
                                                   value="<?= (int)$n['id'] ?>">
                                        </td>
                                        <td>#<?= (int)$n['id'] ?></td>
                                        <td><?= htmlspecialchars($n['competencia']) ?></td>
                                        <td>
                                            <?php if (!empty($n['nome_aluno'])): ?>
                                                <div class="table-primary-text">
                                                    <?= htmlspecialchars($n['nome_aluno']) ?>
                                                </div>
                                                <div class="table-secondary-text">
                                                    Resp.: <?= htmlspecialchars($n['nome_tomador']) ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="table-primary-text">
                                                    <?= htmlspecialchars($n['nome_tomador']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($n['cpf_cnpj']) ?></td>
                                        <td>R$ <?= number_format((float)$n['valor_servico'], 2, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">
                                Enviar selecionadas para homologação
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<script>
    function toggleAll(master) {
        var checkboxes = document.querySelectorAll('input[name="notas[]"]');
        checkboxes.forEach(function (cb) {
            cb.checked = master.checked;
        });
    }
</script>
</body>
</html>

