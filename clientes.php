<?php
require __DIR__ . '/config/db.php';

$mysqli = db_connect();

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nome_tomador' => trim($_POST['nome_tomador'] ?? ''),
        'nome_aluno'   => trim($_POST['nome_aluno'] ?? ''),
        'cpf_cnpj'     => preg_replace('/\D+/', '', $_POST['cpf_cnpj'] ?? ''),
        'email'        => trim($_POST['email'] ?? ''),
        'logradouro'   => trim($_POST['logradouro'] ?? ''),
        'numero'       => trim($_POST['numero'] ?? ''),
        'complemento'  => trim($_POST['complemento'] ?? ''),
        'bairro'       => trim($_POST['bairro'] ?? ''),
        'cidade'       => trim($_POST['cidade'] ?? ''),
        'uf'           => strtoupper(trim($_POST['uf'] ?? '')),
        'cep'          => preg_replace('/\D+/', '', $_POST['cep'] ?? ''),
        'telefone'     => trim($_POST['telefone'] ?? ''),
        'id_interno'   => trim($_POST['id_interno'] ?? ''),
    ];

    // Validações básicas
    if ($data['nome_tomador'] === '') {
        $errors[] = 'Informe o nome do responsável financeiro (tomador).';
    }
    if (!in_array(strlen($data['cpf_cnpj']), [11, 14], true)) {
        $errors[] = 'Informe um CPF (11 dígitos) ou CNPJ (14 dígitos) válido para o tomador.';
    }
    if ($data['email'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Informe um e-mail válido do tomador. Ele será usado para envio da NFS-e.';
    }
    if ($data['logradouro'] === '' || $data['numero'] === '' || $data['bairro'] === '' || $data['cidade'] === '' || $data['uf'] === '') {
        $errors[] = 'Endereço completo (logradouro, número, bairro, cidade e UF) é obrigatório.';
    }
    if (strlen($data['cep']) !== 8) {
        $errors[] = 'Informe um CEP válido (8 dígitos, apenas números).';
    }

    if (!$errors) {
        $sql = 'INSERT INTO clientes (
            nome_tomador, nome_aluno, cpf_cnpj, email,
            logradouro, numero, complemento, bairro, cidade, uf,
            cep, telefone, id_interno, ativo
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1)';

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param(
            'sssssssssssss',
            $data['nome_tomador'],
            $data['nome_aluno'],
            $data['cpf_cnpj'],
            $data['email'],
            $data['logradouro'],
            $data['numero'],
            $data['complemento'],
            $data['bairro'],
            $data['cidade'],
            $data['uf'],
            $data['cep'],
            $data['telefone'],
            $data['id_interno']
        );
        $stmt->execute();
        $stmt->close();

        $success = 'Cliente cadastrado com sucesso.';
    }
}

// Lista rápida dos últimos clientes cadastrados
$result = $mysqli->query('SELECT id, nome_tomador, nome_aluno, cpf_cnpj, email, cidade, uf FROM clientes WHERE ativo = 1 ORDER BY id DESC LIMIT 20');
$clientes = $result->fetch_all(MYSQLI_ASSOC);
$result->close();

function field_value_client(string $key): string
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $value = $_POST[$key] ?? '';
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
    return '';
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Maple Bear | Cadastro de Clientes</title>
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
        <nav class="topnav" data-section="clientes">
            <div class="topnav-indicator"></div>
            <a href="index.php" class="topnav-item" data-section-target="notas">Notas fiscais</a>
            <a href="empresa.php" class="topnav-item" data-section-target="escola">Escola</a>
            <span class="topnav-item active" data-section-target="clientes">Clientes</span>
        </nav>
    </div>
</header>

<main class="page">
    <section class="section">
        <header class="section-header">
            <h3>Cadastro de clientes (tomadores)</h3>
            <p>
                Cadastre aqui os responsáveis financeiros (tomadores) que receberão as NFS-e.
                Os dados deste cadastro serão usados automaticamente na hora da emissão.
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
            <div class="card-header">
                <div>
                    <h4 class="card-title">Dados do tomador</h4>
                    <p class="card-description">
                        Informe os dados completos do responsável financeiro. Eles serão usados para montar
                        o tomador na NFS-e e para envio por e-mail.
                    </p>
                </div>
            </div>

            <div class="card-body">
                <form method="post" class="form-grid">
            <div class="form-group">
                <label for="nome_tomador">Nome do responsável (tomador) *</label>
                <input type="text" id="nome_tomador" name="nome_tomador"
                       value="<?= field_value_client('nome_tomador') ?>" required>
                <small class="field-hint">
                    Nome do responsável financeiro que sairá na NFS-e (ex.: RAQUEL DE AGUIAR).
                </small>
            </div>

            <div class="form-group">
                <label for="nome_aluno">Nome do aluno</label>
                <input type="text" id="nome_aluno" name="nome_aluno"
                       value="<?= field_value_client('nome_aluno') ?>">
                <small class="field-hint">
                    Nome do aluno associado a este responsável (apenas para referência interna).
                </small>
            </div>

            <div class="form-group">
                <label for="cpf_cnpj">CPF/CNPJ do tomador *</label>
                <input type="text" id="cpf_cnpj" name="cpf_cnpj"
                       value="<?= field_value_client('cpf_cnpj') ?>" placeholder="Somente números" required>
                <small class="field-hint">
                    Informe 11 dígitos para CPF ou 14 dígitos para CNPJ, sem pontos ou traços.
                </small>
            </div>

            <div class="form-group">
                <label for="email">E-mail para envio da NFS-e *</label>
                <input type="email" id="email" name="email"
                       value="<?= field_value_client('email') ?>" required>
                <small class="field-hint">
                    A NFS-e será enviada para este e-mail após a emissão (quando o envio automático estiver ativo).
                </small>
            </div>

            <div class="form-group">
                <label for="logradouro">Logradouro *</label>
                <input type="text" id="logradouro" name="logradouro"
                       value="<?= field_value_client('logradouro') ?>" required>
                <small class="field-hint">
                    Rua/Avenida do endereço do tomador.
                </small>
            </div>

            <div class="form-group">
                <label for="numero">Número *</label>
                <input type="text" id="numero" name="numero"
                       value="<?= field_value_client('numero') ?>" required>
                <small class="field-hint">
                    Número do endereço. Use "S/N" se não houver número.
                </small>
            </div>

            <div class="form-group">
                <label for="complemento">Complemento</label>
                <input type="text" id="complemento" name="complemento"
                       value="<?= field_value_client('complemento') ?>">
            </div>

            <div class="form-group">
                <label for="bairro">Bairro *</label>
                <input type="text" id="bairro" name="bairro"
                       value="<?= field_value_client('bairro') ?>" required>
            </div>

            <div class="form-group">
                <label for="cidade">Cidade *</label>
                <input type="text" id="cidade" name="cidade"
                       value="<?= field_value_client('cidade') ?>" required>
            </div>

            <div class="form-group">
                <label for="uf">UF *</label>
                <input type="text" id="uf" name="uf"
                       value="<?= field_value_client('uf') ?>" maxlength="2" required>
            </div>

            <div class="form-group">
                <label for="cep">CEP *</label>
                <input type="text" id="cep" name="cep"
                       value="<?= field_value_client('cep') ?>" placeholder="Somente números" required>
            </div>

            <div class="form-group">
                <label for="telefone">Telefone</label>
                <input type="text" id="telefone" name="telefone"
                       value="<?= field_value_client('telefone') ?>">
            </div>

            <div class="form-group">
                <label for="id_interno">ID interno / RA do aluno</label>
                <input type="text" id="id_interno" name="id_interno"
                       value="<?= field_value_client('id_interno') ?>">
                <small class="field-hint">
                    Código usado pelo financeiro ou pelo sistema acadêmico para identificar este cliente/aluno.
                </small>
            </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            Salvar cliente
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <section class="section">
        <header class="section-header">
            <h3>Últimos clientes cadastrados</h3>
            <p>Lista dos 20 clientes ativos mais recentes.</p>
        </header>

        <div class="card">
            <div class="card-body">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Responsável</th>
                        <th>Aluno</th>
                        <th>CPF/CNPJ</th>
                        <th>E-mail</th>
                        <th>Cidade/UF</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$clientes): ?>
                        <tr>
                            <td colspan="6" class="table-empty">
                                Nenhum cliente cadastrado ainda.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clientes as $c): ?>
                            <tr>
                                <td><?= htmlspecialchars($c['id'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($c['nome_tomador'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($c['nome_aluno'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($c['cpf_cnpj'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($c['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($c['cidade'], ENT_QUOTES, 'UTF-8') ?> / <?= htmlspecialchars($c['uf'], ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>

<script>
(function() {
    var nav = document.querySelector('.topnav[data-section="clientes"]');
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

