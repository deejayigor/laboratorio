<?php
require __DIR__ . '/config/db.php';

$mysqli = db_connect();

// Carrega config base (prestador/serviço/certificado)
$configBase = require __DIR__ . '/config_nfse_escola.php';
require_once __DIR__ . '/classes/DPSBuilder.php';
require_once __DIR__ . '/classes/XMLSigner.php';

$errors = [];
$success = null;
$detalhes = [];

// Carrega empresa (se houver) para sobrepor config de prestador/serviço/certificado
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
    // Sobrescreve dados do prestador
    $configBase['prestador']['cnpj']               = preg_replace('/\D/', '', $empresa['cnpj']);
    $configBase['prestador']['inscricao_municipal'] = $empresa['inscricao_municipal'] ?? '';
    $configBase['prestador']['razao_social']        = $empresa['razao_social'];
    $configBase['prestador']['nome_fantasia']       = $empresa['nome_fantasia'] ?: $empresa['razao_social'];
    $configBase['prestador']['logradouro']          = $empresa['logradouro'];
    $configBase['prestador']['numero']              = $empresa['numero'];
    $configBase['prestador']['complemento']         = $empresa['complemento'] ?? '';
    $configBase['prestador']['bairro']              = $empresa['bairro'];
    $configBase['prestador']['codigo_municipio']    = $empresa['codigo_municipio_ibge'];
    $configBase['prestador']['uf']                  = $empresa['uf'];
    $configBase['prestador']['cep']                 = $empresa['cep'];
    $configBase['prestador']['telefone']            = $empresa['telefone'] ?? '';
    $configBase['prestador']['email']               = $empresa['email_financeiro'] ?? '';

    // Sobrescreve dados de serviço (normalizando códigos para apenas dígitos)
    $configBase['servico']['item_lista_servico']          = $empresa['item_lista_servico'];
    $configBase['servico']['codigo_tributacao_municipal'] = $empresa['codigo_tributacao_municipal'];
    // cTribNac deve seguir o padrão TSCodTribNac (somente dígitos, ex.: 080101)
    $configBase['servico']['codigo_tributacao_nacional']  = preg_replace('/\D/', '', $empresa['codigo_tributacao_nacional']);
    $configBase['servico']['codigo_nbs']                  = $empresa['codigo_nbs'] ?: $configBase['servico']['codigo_nbs'];
    $configBase['servico']['aliquota_iss']                = (float)$empresa['aliquota_iss'];
    if (!empty($empresa['descricao_servico_padrao'])) {
        $configBase['servico']['descricao_padrao'] = $empresa['descricao_servico_padrao'];
    }

    // Senha do certificado: permitimos sobrescrever pela tela da escola
    if (!empty($empresa['certificado_senha'])) {
        $configBase['nacional']['certificado_senha'] = $empresa['certificado_senha'];
    }
}

// Caminho base para salvar XMLs
$xmlDir = rtrim($configBase['debug']['xml_path'] ?? (__DIR__ . '/data/xml/'), '/\\') . DIRECTORY_SEPARATOR;
if (!is_dir($xmlDir)) {
    @mkdir($xmlDir, 0755, true);
}

// POST: gerar XML para notas selecionadas
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
        $errors[] = 'Selecione ao menos uma nota para gerar o XML.';
    } elseif (!$empresa) {
        $errors[] = 'Antes de gerar o XML, cadastre os dados da escola na tela "Escola".';
    } else {
        // Busca notas + clientes
        $placeholders = implode(',', array_fill(0, count($idsFiltrados), '?'));
        $tipos        = str_repeat('i', count($idsFiltrados));

        $sql = "SELECT n.id, n.competencia, n.valor_servico, n.descricao_servico,
                       c.nome_tomador, c.nome_aluno, c.cpf_cnpj, c.email,
                       c.logradouro, c.numero, c.bairro, c.cidade, c.uf, c.cep, c.telefone
                FROM notas n
                JOIN clientes c ON c.id = n.cliente_id
                WHERE n.id IN ($placeholders)";

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param($tipos, ...$idsFiltrados);
        $stmt->execute();
        $res = $stmt->get_result();
        $notasSelecionadas = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($notasSelecionadas)) {
            $errors[] = 'Nenhuma nota encontrada para os IDs selecionados.';
        } else {
            foreach ($notasSelecionadas as $nota) {
                $idNota = (int)$nota['id'];

                // Monta estrutura esperada pelo DPSBuilder (dados_fatura)
                $cpfCnpj = preg_replace('/\D/', '', $nota['cpf_cnpj']);
                $fisJur  = strlen($cpfCnpj) === 14 ? 'J' : 'F';

                // Nome do tomador (responsável). Inclui aluno se existir.
                $nomeTomador = $nota['nome_tomador'];
                if (!empty($nota['nome_aluno'])) {
                    $nomeTomador .= ' - ' . $nota['nome_aluno'];
                }

                $ddd = '0';
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
                    'numero_dps'       => $idNota, // uso interno sequencial por nota
                ];

                try {
                    $builder       = new DPSBuilder($configBase, $dadosFatura);
                    $xmlDps        = $builder->montarDPS();
                    $xmlDpsPretty  = $builder->getXMLFormatado();

                    // Salva XML DPS (não assinado)
                    $baseName = 'DPS_nota_' . $idNota . '_' . date('Ymd_His');
                    $fileDps  = $xmlDir . $baseName . '.xml';
                    file_put_contents($fileDps, $xmlDpsPretty);

                    // Assina XML com o certificado configurado
                    $signer = new XMLSigner(
                        $configBase['nacional']['certificado_path'],
                        $configBase['nacional']['certificado_senha']
                    );
                    $xmlAssinado = $signer->assinarXML($xmlDps, 'infDPS');

                    // Reformatar XML assinado para leitura
                    $domSigned = new DOMDocument('1.0', 'UTF-8');
                    $domSigned->preserveWhiteSpace = false;
                    $domSigned->formatOutput       = true;
                    $domSigned->loadXML($xmlAssinado);
                    $xmlAssinadoPretty = $domSigned->saveXML();

                    $fileSigned = $xmlDir . $baseName . '_assinado.xml';
                    file_put_contents($fileSigned, $xmlAssinadoPretty);

                    $detalhes[] = [
                        'id'          => $idNota,
                        'arquivo_dps' => basename($fileDps),
                        'arquivo_ass' => basename($fileSigned),
                    ];
                } catch (Throwable $e) {
                    $errors[] = 'Erro ao gerar XML da nota #' . $idNota . ': ' . $e->getMessage();
                }
            }

            if ($detalhes && !$errors) {
                $success = 'XML(s) gerados e assinados com sucesso. Arquivos salvos em nfse_escola/data/xml/.';
            }
        }
    }
}

// Carrega notas pendentes para exibição
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
    <title>Maple Bear | Simulação XML NFS-e</title>
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
                <h2 class="section-title">Simulação de XML DPS / NFS-e</h2>
                <p class="section-description">
                    Gera o XML DPS para as notas <strong>pendentes</strong> e salva os arquivos em
                    <code>nfse_escola/data/xml/</code>, já com assinatura digital. Não envia nada ao Portal Nacional.
                </p>
            </div>
            <div class="section-actions">
                <a href="index.php" class="btn">Voltar ao painel</a>
            </div>
        </header>

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <strong>Erro ao gerar XML.</strong>
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
                <?php if ($detalhes): ?>
                    <ul>
                        <?php foreach ($detalhes as $d): ?>
                            <li>
                                Nota #<?= (int)$d['id'] ?>:
                                XML: <code><?= htmlspecialchars($d['arquivo_dps']) ?></code>,
                                Assinado: <code><?= htmlspecialchars($d['arquivo_ass']) ?></code>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Notas pendentes para simulação</h3>
                    <p class="card-description">
                        Selecione quais notas pendentes terão o XML gerado. Isso não altera o status da nota,
                        apenas cria os arquivos XML para conferência técnica.
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
                                Gerar XML para selecionadas
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

