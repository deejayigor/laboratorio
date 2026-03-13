<?php
require __DIR__ . '/config/db.php';

$mysqli = db_connect();

// Carrega registro existente (se houver)
$stmt = $mysqli->prepare('SELECT * FROM empresa LIMIT 1');
$stmt->execute();
$empresa = $stmt->get_result()->fetch_assoc();
$stmt->close();

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'razao_social'               => trim($_POST['razao_social'] ?? ''),
        'nome_fantasia'             => trim($_POST['nome_fantasia'] ?? ''),
        'cnpj'                      => preg_replace('/\D+/', '', $_POST['cnpj'] ?? ''),
        'inscricao_municipal'       => trim($_POST['inscricao_municipal'] ?? ''),
        'logradouro'                => trim($_POST['logradouro'] ?? ''),
        'numero'                    => trim($_POST['numero'] ?? ''),
        'complemento'               => trim($_POST['complemento'] ?? ''),
        'bairro'                    => trim($_POST['bairro'] ?? ''),
        'cidade'                    => trim($_POST['cidade'] ?? ''),
        'uf'                        => strtoupper(trim($_POST['uf'] ?? '')),
        'cep'                       => preg_replace('/\D+/', '', $_POST['cep'] ?? ''),
        'telefone'                  => trim($_POST['telefone'] ?? ''),
        'email_financeiro'          => trim($_POST['email_financeiro'] ?? ''),
        'codigo_municipio_ibge'     => preg_replace('/\D+/', '', $_POST['codigo_municipio_ibge'] ?? ''),
        'item_lista_servico'        => trim($_POST['item_lista_servico'] ?? ''),
        'codigo_tributacao_municipal'=> trim($_POST['codigo_tributacao_municipal'] ?? ''),
        'codigo_tributacao_nacional'=> trim($_POST['codigo_tributacao_nacional'] ?? ''),
        'codigo_nbs'                => trim($_POST['codigo_nbs'] ?? ''),
        'aliquota_iss'              => str_replace(',', '.', trim($_POST['aliquota_iss'] ?? '0')),
        'descricao_servico_padrao'  => trim($_POST['descricao_servico_padrao'] ?? ''),
        'certificado_caminho'       => trim($_POST['certificado_caminho'] ?? ''),
        'certificado_senha'         => trim($_POST['certificado_senha'] ?? ''),
        'smtp_host'                 => trim($_POST['smtp_host'] ?? ''),
        'smtp_porta'                => (int)($_POST['smtp_porta'] ?? 0),
        'smtp_usuario'              => trim($_POST['smtp_usuario'] ?? ''),
        'smtp_senha'                => trim($_POST['smtp_senha'] ?? ''),
        'smtp_from_email'           => trim($_POST['smtp_from_email'] ?? ''),
        'smtp_from_nome'            => trim($_POST['smtp_from_nome'] ?? ''),
    ];

    // Validações básicas
    if ($data['razao_social'] === '') {
        $errors[] = 'Informe a razão social da escola.';
    }
    if (strlen($data['cnpj']) !== 14) {
        $errors[] = 'Informe um CNPJ válido (14 dígitos, apenas números).';
    }
    if ($data['cidade'] === '' || $data['uf'] === '') {
        $errors[] = 'Cidade e UF são obrigatórios.';
    }
    if (strlen($data['cep']) !== 8) {
        $errors[] = 'Informe um CEP válido (8 dígitos, apenas números).';
    }
    if ($data['codigo_municipio_ibge'] === '') {
        $errors[] = 'Informe o código IBGE do município (7 dígitos).';
    }
    if ($data['descricao_servico_padrao'] === '') {
        $errors[] = 'Informe a descrição padrão do serviço que aparecerá na NFS-e.';
    }

    if (!$errors) {
        if ($empresa) {
            $sql = 'UPDATE empresa SET
                razao_social = ?, nome_fantasia = ?, cnpj = ?, inscricao_municipal = ?,
                logradouro = ?, numero = ?, complemento = ?, bairro = ?, cidade = ?, uf = ?,
                cep = ?, telefone = ?, email_financeiro = ?, codigo_municipio_ibge = ?,
                item_lista_servico = ?, codigo_tributacao_municipal = ?, codigo_tributacao_nacional = ?,
                codigo_nbs = ?, aliquota_iss = ?, descricao_servico_padrao = ?,
                certificado_caminho = ?, certificado_senha = ?,
                smtp_host = ?, smtp_porta = ?, smtp_usuario = ?, smtp_senha = ?,
                smtp_from_email = ?, smtp_from_nome = ?
                WHERE id = ?';

            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param(
                'sssssssssssssssssssdssssisssi',
                $data['razao_social'],
                $data['nome_fantasia'],
                $data['cnpj'],
                $data['inscricao_municipal'],
                $data['logradouro'],
                $data['numero'],
                $data['complemento'],
                $data['bairro'],
                $data['cidade'],
                $data['uf'],
                $data['cep'],
                $data['telefone'],
                $data['email_financeiro'],
                $data['codigo_municipio_ibge'],
                $data['item_lista_servico'],
                $data['codigo_tributacao_municipal'],
                $data['codigo_tributacao_nacional'],
                $data['codigo_nbs'],
                $data['aliquota_iss'],
                $data['descricao_servico_padrao'],
                $data['certificado_caminho'],
                $data['certificado_senha'],
                $data['smtp_host'],
                $data['smtp_porta'],
                $data['smtp_usuario'],
                $data['smtp_senha'],
                $data['smtp_from_email'],
                $data['smtp_from_nome'],
                $empresa['id']
            );
            $stmt->execute();
            $stmt->close();
        } else {
            $sql = 'INSERT INTO empresa (
                razao_social, nome_fantasia, cnpj, inscricao_municipal,
                logradouro, numero, complemento, bairro, cidade, uf,
                cep, telefone, email_financeiro, codigo_municipio_ibge,
                item_lista_servico, codigo_tributacao_municipal, codigo_tributacao_nacional,
                codigo_nbs, aliquota_iss, descricao_servico_padrao,
                certificado_caminho, certificado_senha,
                smtp_host, smtp_porta, smtp_usuario, smtp_senha,
                smtp_from_email, smtp_from_nome
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';

            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param(
                'sssssssssssssssssssdssssisss',
                $data['razao_social'],
                $data['nome_fantasia'],
                $data['cnpj'],
                $data['inscricao_municipal'],
                $data['logradouro'],
                $data['numero'],
                $data['complemento'],
                $data['bairro'],
                $data['cidade'],
                $data['uf'],
                $data['cep'],
                $data['telefone'],
                $data['email_financeiro'],
                $data['codigo_municipio_ibge'],
                $data['item_lista_servico'],
                $data['codigo_tributacao_municipal'],
                $data['codigo_tributacao_nacional'],
                $data['codigo_nbs'],
                $data['aliquota_iss'],
                $data['descricao_servico_padrao'],
                $data['certificado_caminho'],
                $data['certificado_senha'],
                $data['smtp_host'],
                $data['smtp_porta'],
                $data['smtp_usuario'],
                $data['smtp_senha'],
                $data['smtp_from_email'],
                $data['smtp_from_nome']
            );
            $stmt->execute();
            $stmt->close();
        }

        $success = 'Dados da escola salvos com sucesso.';

        // Recarrega dados atualizados
        $stmt = $mysqli->prepare('SELECT * FROM empresa LIMIT 1');
        $stmt->execute();
        $empresa = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

function field_value(array $src = null, string $key, string $default = ''): string
{
    if ($src && isset($src[$key]) && $src[$key] !== null) {
        return htmlspecialchars((string)$src[$key], ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars($default, ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Maple Bear | Dados da Escola</title>
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
        <nav class="topnav" data-section="escola">
            <div class="topnav-indicator"></div>
            <a href="index.php" class="topnav-item" data-section-target="notas">Notas fiscais</a>
            <span class="topnav-item active" data-section-target="escola">Escola</span>
            <a href="clientes.php" class="topnav-item" data-section-target="clientes">Clientes</a>
        </nav>
    </div>
</header>

<main class="page">
    <section class="section">
        <header class="section-header">
            <h3>Dados da escola para NFS-e</h3>
            <p>
                Preencha os dados exatamente como constam no cadastro da prefeitura e no certificado digital.
                Esses dados serão usados para montar o XML da NFS-e e enviar para o Padrão Nacional.
            </p>
        </header>

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <strong>Revise os campos abaixo:</strong>
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php elseif ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header card-header-with-actions">
                <div>
                    <h4 class="card-title">Configuração principal</h4>
                    <p class="card-description">
                        Esses dados identificam a escola como prestador de serviços perante o Portal Nacional
                        da NFS-e. Use sempre as informações oficiais da prefeitura e da contabilidade.
                    </p>
                </div>
                <div class="card-actions">
                    <button type="button" class="btn btn-primary" onclick="preencherComExemplo()">
                        Preencher com exemplo da NFS
                    </button>
                </div>
            </div>

            <div class="card-body">
                <form method="post" class="form-grid">
            <div class="form-group">
                <label for="razao_social">Razão social *</label>
                <input type="text" id="razao_social" name="razao_social"
                       value="<?= field_value($empresa, 'razao_social') ?>"
                       placeholder="Ex.: MB BOTUCATU INSTITUICAO DE ENSINO LTDA" required>
                <small class="field-hint">
                    Nome completo da escola exatamente como no CNPJ e no cadastro da prefeitura.
                </small>
            </div>

            <div class="form-group">
                <label for="nome_fantasia">Nome fantasia</label>
                <input type="text" id="nome_fantasia" name="nome_fantasia"
                       value="<?= field_value($empresa, 'nome_fantasia') ?>"
                       placeholder="Ex.: Maple Bear Botucatu">
                <small class="field-hint">
                    Como a escola é conhecida comercialmente (opcional).
                </small>
            </div>

            <div class="form-group">
                <label for="cnpj">CNPJ *</label>
                <input type="text" id="cnpj" name="cnpj"
                       value="<?= field_value($empresa, 'cnpj') ?>"
                       placeholder="Ex.: 31653761000176 (somente números)" required>
                <small class="field-hint">
                    CNPJ do prestador que constará na NFS-e (14 dígitos, sem pontos ou traços).
                </small>
            </div>

            <div class="form-group">
                <label for="inscricao_municipal">Inscrição municipal</label>
                <input type="text" id="inscricao_municipal" name="inscricao_municipal"
                       value="<?= field_value($empresa, 'inscricao_municipal') ?>">
                <small class="field-hint">
                    Inscrição municipal fornecida pela prefeitura. Se a escola não tiver, deixe em branco.
                </small>
            </div>

            <div class="form-group">
                <label for="logradouro">Logradouro *</label>
                <input type="text" id="logradouro" name="logradouro"
                       value="<?= field_value($empresa, 'logradouro') ?>"
                       placeholder="Ex.: DOUTOR COSTA LEITE" required>
                <small class="field-hint">
                    Rua/Avenida conforme aparece no comprovante de endereço.
                </small>
            </div>

            <div class="form-group">
                <label for="numero">Número *</label>
                <input type="text" id="numero" name="numero"
                       value="<?= field_value($empresa, 'numero') ?>"
                       placeholder="Ex.: 2479" required>
                <small class="field-hint">
                    Número do endereço. Use "S/N" se não houver número.
                </small>
            </div>

            <div class="form-group">
                <label for="complemento">Complemento</label>
                <input type="text" id="complemento" name="complemento"
                       value="<?= field_value($empresa, 'complemento') ?>">
            </div>

            <div class="form-group">
                <label for="bairro">Bairro *</label>
                <input type="text" id="bairro" name="bairro"
                       value="<?= field_value($empresa, 'bairro') ?>"
                       placeholder="Ex.: VILA NOGUEIRA" required>
            </div>

            <div class="form-group">
                <label for="cidade">Cidade *</label>
                <input type="text" id="cidade" name="cidade"
                       value="<?= field_value($empresa, 'cidade') ?>"
                       placeholder="Ex.: Botucatu" required>
                <small class="field-hint">
                    Para a unidade de Botucatu, informe "Botucatu".
                </small>
            </div>

            <div class="form-group">
                <label for="uf">UF *</label>
                <input type="text" id="uf" name="uf"
                       value="<?= field_value($empresa, 'uf') ?>"
                       placeholder="Ex.: SP" maxlength="2" required>
                <small class="field-hint">
                    Sigla do estado (ex.: SP).
                </small>
            </div>

            <div class="form-group">
                <label for="cep">CEP *</label>
                <input type="text" id="cep" name="cep"
                       value="<?= field_value($empresa, 'cep') ?>"
                       placeholder="Ex.: 18606820 (somente números)" required>
            </div>

            <div class="form-group">
                <label for="telefone">Telefone</label>
                <input type="text" id="telefone" name="telefone"
                       value="<?= field_value($empresa, 'telefone') ?>"
                       placeholder="Ex.: (14) 9656-0809">
                <small class="field-hint">
                    Telefone principal da escola (apenas informativo no XML).
                </small>
            </div>

            <div class="form-group">
                <label for="email_financeiro">E-mail do financeiro</label>
                <input type="email" id="email_financeiro" name="email_financeiro"
                       value="<?= field_value($empresa, 'email_financeiro') ?>"
                       placeholder="Ex.: tjsegato@gmail.com">
                <small class="field-hint">
                    E-mail usado como contato financeiro e cópia das NFS-e, se configurado.
                </small>
            </div>

            <div class="form-group">
                <label for="codigo_municipio_ibge">Código IBGE do município *</label>
                <input type="text" id="codigo_municipio_ibge" name="codigo_municipio_ibge"
                       value="<?= field_value($empresa, 'codigo_municipio_ibge') ?>"
                       placeholder="Ex.: 3507506" required>
                <small class="field-hint">
                    Código IBGE de 7 dígitos do município da escola (ex.: Botucatu-SP = 3507506).
                </small>
            </div>

            <div class="form-group">
                <label for="item_lista_servico">Item da lista de serviços *</label>
                <input type="text" id="item_lista_servico" name="item_lista_servico"
                       value="<?= field_value($empresa, 'item_lista_servico') ?>"
                       placeholder="Ex.: 08.01">
                <small class="field-hint">
                    Código do item da lista de serviços definido pela legislação municipal
                    (na NFS da escola está como &quot;Ensino regular pré-escolar, fundamental e médio&quot;).
                </small>
            </div>

            <div class="form-group">
                <label for="codigo_tributacao_municipal">Cód. tributação municipal *</label>
                <input type="text" id="codigo_tributacao_municipal" name="codigo_tributacao_municipal"
                       value="<?= field_value($empresa, 'codigo_tributacao_municipal') ?>">
                <small class="field-hint">
                    Código usado pela prefeitura para identificar o tipo de serviço prestado.
                    Normalmente consta na NFS-e emitida manualmente.
                </small>
            </div>

            <div class="form-group">
                <label for="codigo_tributacao_nacional">Cód. tributação nacional *</label>
                <input type="text" id="codigo_tributacao_nacional" name="codigo_tributacao_nacional"
                       value="<?= field_value($empresa, 'codigo_tributacao_nacional') ?>"
                       placeholder="Ex.: 08.01.01">
                <small class="field-hint">
                    Código nacional da atividade (CNAE/NBS) conforme Padrão Nacional.
                    Na NFS exemplo da escola aparece como 08.01.01.
                </small>
            </div>

            <div class="form-group">
                <label for="codigo_nbs">Código NBS</label>
                <input type="text" id="codigo_nbs" name="codigo_nbs"
                       value="<?= field_value($empresa, 'codigo_nbs') ?>"
                       placeholder="Ex.: 122012000">
                <small class="field-hint">
                    Se a prefeitura exigir NBS específico, informe aqui. Caso contrário, deixe em branco.
                </small>
            </div>

            <div class="form-group">
                <label for="aliquota_iss">Alíquota de ISS (%) *</label>
                <input type="text" id="aliquota_iss" name="aliquota_iss"
                       value="<?= field_value($empresa, 'aliquota_iss') ?>"
                       placeholder="Ex.: 2,06">
                <small class="field-hint">
                    Percentual de ISS a ser aplicado sobre o valor do serviço (ex.: 5,00%).
                </small>
            </div>

            <div class="form-group form-group-full">
                <label for="descricao_servico_padrao">Descrição padrão do serviço *</label>
                <textarea id="descricao_servico_padrao" name="descricao_servico_padrao" rows="3" required
                          placeholder="Ex.: PRESTAÇÃO DE SERVIÇOS EDUCACIONAIS – ENSINO FUNDAMENTAL"><?= field_value($empresa, 'descricao_servico_padrao') ?></textarea>
                <small class="field-hint">
                    Texto que aparecerá na descrição da NFS-e. Ex.: "Mensalidade escolar ref. {competencia}".
                    Podemos depois montar automaticamente a referência do mês/ano.
                </small>
            </div>

            <div class="form-group form-group-full">
                <label for="certificado_caminho">Caminho do certificado A1 (.pfx) *</label>
                <input type="text" id="certificado_caminho" name="certificado_caminho"
                       value="<?= field_value($empresa, 'certificado_caminho') ?>" placeholder="Ex.: C:\certificados\escola.pfx">
                <small class="field-hint">
                    Caminho completo do arquivo .pfx no servidor onde o sistema rodará (no XAMPP da escola).
                    Depois, vamos validar esse caminho com uma tela de diagnóstico.
                </small>
            </div>

            <div class="form-group">
                <label for="certificado_senha">Senha do certificado A1 *</label>
                <input type="password" id="certificado_senha" name="certificado_senha"
                       value="<?= field_value($empresa, 'certificado_senha') ?>">
                <small class="field-hint">
                    Senha do arquivo .pfx fornecida pela autoridade certificadora.
                </small>
            </div>

            <div class="form-group form-group-full">
                <h4 class="fieldset-title">Configurações de e-mail (opcional)</h4>
                <p class="field-hint">
                    Preencha apenas se desejar que o sistema envie a NFS-e por e-mail automaticamente
                    para o responsável. Você pode deixar em branco e configurar depois.
                </p>
            </div>

            <div class="form-group">
                <label for="smtp_host">SMTP - Host</label>
                <input type="text" id="smtp_host" name="smtp_host"
                       value="<?= field_value($empresa, 'smtp_host') ?>" placeholder="Ex.: smtp.seudominio.com.br">
            </div>

            <div class="form-group">
                <label for="smtp_porta">SMTP - Porta</label>
                <input type="number" id="smtp_porta" name="smtp_porta"
                       value="<?= field_value($empresa, 'smtp_porta') ?>" placeholder="Ex.: 465 ou 587">
            </div>

            <div class="form-group">
                <label for="smtp_usuario">SMTP - Usuário</label>
                <input type="text" id="smtp_usuario" name="smtp_usuario"
                       value="<?= field_value($empresa, 'smtp_usuario') ?>">
            </div>

            <div class="form-group">
                <label for="smtp_senha">SMTP - Senha</label>
                <input type="password" id="smtp_senha" name="smtp_senha"
                       value="<?= field_value($empresa, 'smtp_senha') ?>">
            </div>

            <div class="form-group">
                <label for="smtp_from_email">E-mail remetente</label>
                <input type="email" id="smtp_from_email" name="smtp_from_email"
                       value="<?= field_value($empresa, 'smtp_from_email') ?>">
                <small class="field-hint">
                    Endereço de e-mail que aparecerá como remetente das NFS-e.
                </small>
            </div>

            <div class="form-group">
                <label for="smtp_from_nome">Nome remetente</label>
                <input type="text" id="smtp_from_nome" name="smtp_from_nome"
                       value="<?= field_value($empresa, 'smtp_from_nome') ?>">
            </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            Salvar dados da escola
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</main>

<script>
function preencherComExemplo() {
    const campos = {
        razao_social: 'MB BOTUCATU INSTITUICAO DE ENSINO LTDA',
        nome_fantasia: 'Maple Bear Botucatu',
        cnpj: '31653761000176',
        inscricao_municipal: '',
        logradouro: 'DOUTOR COSTA LEITE',
        numero: '2479',
        complemento: '',
        bairro: 'VILA NOGUEIRA',
        cidade: 'Botucatu',
        uf: 'SP',
        cep: '18606820',
        telefone: '(14) 9656-0809',
        email_financeiro: 'tjsegato@gmail.com',
        codigo_municipio_ibge: '3507506',
        item_lista_servico: '08.01',
        codigo_tributacao_municipal: '',
        codigo_tributacao_nacional: '08.01.01',
        codigo_nbs: '122012000',
        aliquota_iss: '2,06',
        descricao_servico_padrao: 'PRESTAÇÃO DE SERVIÇOS EDUCACIONAIS – ENSINO FUNDAMENTAL'
    };

    Object.keys(campos).forEach(function (id) {
        const el = document.getElementById(id);
        if (el) {
            el.value = campos[id];
        }
    });
}

(function() {
    var nav = document.querySelector('.topnav[data-section="escola"]');
    if (!nav) return;
    var indicator = nav.querySelector('.topnav-indicator');
    var active = nav.querySelector('.topnav-item.active');
    if (!indicator || !active) return;

    function positionIndicator(el) {
        var rect = el.getBoundingClientRect();
        var navRect = nav.getBoundingClientRect();
        var width = rect.width;
        var offsetLeft = rect.left - navRect.left;
        indicator.style.width = width + 'px';
        indicator.style.transform = 'translateX(' + offsetLeft + 'px)';
    }

    // posição inicial: começa na aba anterior (se existir), sem animação,
    // e depois anima suavemente até a aba atual
    var previousSection = window.localStorage.getItem('nfse_nav_section');
    var initialTarget = null;
    if (previousSection) {
        initialTarget = nav.querySelector('[data-section-target="' + previousSection + '"]');
    }

    if (initialTarget) {
        positionIndicator(initialTarget);
    } else {
        positionIndicator(active);
    }

    // ativa animação após posicionar a primeira vez
    requestAnimationFrame(function () {
        indicator.classList.add('is-ready');
        positionIndicator(active);
    });

    // guarda a seção clicada para a próxima página
    nav.querySelectorAll('[data-section-target]').forEach(function (item) {
        item.addEventListener('click', function () {
            var section = item.getAttribute('data-section-target');
            if (section) {
                window.localStorage.setItem('nfse_nav_section', section);
            }
        });
    });
})();
</script>
</body>
</html>

