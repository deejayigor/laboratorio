<?php
require __DIR__ . '/config/db.php';

$mysqli = db_connect();

$errors = [];
$success = null;
$resumo = null;

// Carrega descrição padrão do serviço, se existir
$descricaoPadrao = 'Mensalidade escolar';
try {
    $res = $mysqli->query('SELECT descricao_servico_padrao FROM empresa LIMIT 1');
    if ($res && $row = $res->fetch_assoc()) {
        if (!empty($row['descricao_servico_padrao'])) {
            $descricaoPadrao = $row['descricao_servico_padrao'];
        }
    }
} catch (Throwable $e) {
    // mantém descrição padrão simples
}

// Lista de clientes ativos
$clientes = [];
try {
    $res = $mysqli->query("SELECT id, nome_tomador, nome_aluno FROM clientes WHERE ativo = 1 ORDER BY nome_tomador");
    if ($res) {
        $clientes = $res->fetch_all(MYSQLI_ASSOC);
        $res->close();
    }
} catch (Throwable $e) {
    $clientes = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $competencia = trim($_POST['competencia'] ?? '');
    $valorStr = str_replace(['.', ' '], ['', ''], $_POST['valor_servico'] ?? '');
    $valorStr = str_replace(',', '.', $valorStr);
    $valor = (float)$valorStr;
    $descricaoCustom = trim($_POST['descricao_servico'] ?? '');
    $escopo = $_POST['escopo'] ?? 'todos';
    $selecionados = $_POST['clientes'] ?? [];

    if (!preg_match('/^\d{2}\/\d{4}$/', $competencia)) {
        $errors[] = 'Informe a competência no formato MM/AAAA.';
    }
    if ($valor <= 0) {
        $errors[] = 'Informe um valor de serviço maior que zero.';
    }
    if ($escopo === 'selecionados' && empty($selecionados)) {
        $errors[] = 'Selecione ao menos um cliente ou escolha a opção \"Todos os clientes ativos\".';
    }

    if (!$errors) {
        $ids = [];
        if ($escopo === 'todos') {
            foreach ($clientes as $c) {
                $ids[] = (int)$c['id'];
            }
        } else {
            foreach ($selecionados as $id) {
                $ids[] = (int)$id;
            }
        }

        $criadas = 0;
        $stmtInsert = $mysqli->prepare(
            "INSERT INTO notas (cliente_id, competencia, valor_servico, descricao_servico, status)
             VALUES (?,?,?,?, 'PENDENTE')"
        );

        foreach ($ids as $cid) {
            if ($cid <= 0) {
                continue;
            }
            if ($descricaoCustom !== '') {
                $descricao = str_replace('{competencia}', $competencia, $descricaoCustom);
            } else {
                $descricao = $descricaoPadrao . ' ref. ' . $competencia;
            }
            $stmtInsert->bind_param('isds', $cid, $competencia, $valor, $descricao);
            $stmtInsert->execute();
            $criadas++;
        }

        $stmtInsert->close();

        $success = 'Geração concluída.';
        $resumo = [
            'criadas' => $criadas,
            'total' => count($ids)
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Maple Bear | Gerar notas da competência</title>
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
            <span class="topnav-item active" data-section-target="notas">Notas fiscais</span>
            <a href="empresa.php" class="topnav-item" data-section-target="escola">Escola</a>
            <a href="clientes.php" class="topnav-item" data-section-target="clientes">Clientes</a>
        </nav>
    </div>
</header>

<main class="page">
    <section class="section">
        <header class="section-header">
            <h3>Gerar notas da competência</h3>
            <p>
                Use esta tela para criar as notas fiscais que ficarão na fila de emissão NFS-e.
                Cada cliente selecionado receberá uma nota com a competência e o valor informados.
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
                <?php if ($resumo): ?>
                    <br>
                    <small>
                        Notas criadas: <?= (int)$resumo['criadas'] ?> ·
                        Clientes considerados: <?= (int)$resumo['total'] ?>
                    </small>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header card-header-with-actions">
                <div>
                    <h4 class="card-title">Parâmetros da geração</h4>
                    <p class="card-description">
                        Escolha a competência (mês/ano) e o valor do serviço que será aplicado
                        a cada nota. Depois selecione se quer gerar para todos os clientes ativos
                        ou apenas alguns.
                    </p>
                </div>
                <div class="card-actions">
                    <a href="index.php" class="btn">
                        Voltar para o painel
                    </a>
                </div>
            </div>

            <div class="card-body">
                <form method="post" class="form-grid">
                    <div class="form-group">
                        <label for="competencia">Competência (MM/AAAA) *</label>
                        <input type="text" id="competencia" name="competencia"
                               value="<?= htmlspecialchars($_POST['competencia'] ?? date('m/Y'), ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="Ex.: <?= date('m/Y') ?>" required>
                        <small class="field-hint">
                            Mês/ano de referência da mensalidade. Ex.: 03/2026.
                        </small>
                    </div>

                    <div class="form-group form-group-full">
                        <label for="descricao_servico">Descrição dos serviços</label>
                        <textarea id="descricao_servico" name="descricao_servico" rows="2"
                                  placeholder="Ex.: Mensalidade escolar referente ao mês {competencia}"><?= htmlspecialchars($_POST['descricao_servico'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        <small class="field-hint">
                            Texto que aparecerá na descrição da NFS-e. Use <code>{competencia}</code> para o sistema
                            substituir automaticamente pelo mês/ano informado (ex.: \"Mensalidade escolar referente ao mês 03/2026\").
                            Se deixar em branco, será usada a descrição padrão da escola com a competência ao final.
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="valor_servico">Valor do serviço (R$) *</label>
                        <input type="text" id="valor_servico" name="valor_servico"
                               value="<?= htmlspecialchars($_POST['valor_servico'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="Ex.: 13680,00" required>
                        <small class="field-hint">
                            Valor bruto da mensalidade que será aplicado para cada cliente selecionado.
                        </small>
                    </div>

                    <div class="form-group form-group-full">
                        <h4 class="fieldset-title">Escopo dos clientes</h4>
                        <p class="field-hint">
                            Você pode gerar notas para todos os clientes ativos ou escolher manualmente
                            apenas alguns responsáveis.
                        </p>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="radio" name="escopo" value="todos"
                                   <?= (($_POST['escopo'] ?? 'todos') === 'todos') ? 'checked' : '' ?>>
                            Todos os clientes ativos
                        </label>
                    </div>

                    <div class="form-group form-group-full">
                        <label>
                            <input type="radio" name="escopo" value="selecionados"
                                   <?= (($_POST['escopo'] ?? '') === 'selecionados') ? 'checked' : '' ?>>
                            Somente clientes selecionados abaixo
                        </label>
                        <small class="field-hint">
                            Marque esta opção para escolher manualmente os clientes na lista.
                        </small>
                    </div>

                    <div class="form-group form-group-full">
                        <h4 class="fieldset-title">Clientes ativos</h4>
                        <p class="field-hint">
                            Lista dos clientes ativos cadastrados. Use as caixas de seleção apenas se tiver escolhido
                            a opção \"Somente clientes selecionados\".
                        </p>
                    </div>

                    <div class="form-group form-group-full">
                        <div class="card card-table">
                            <div class="card-body">
                                <table class="table">
                                    <thead>
                                    <tr>
                                        <th></th>
                                        <th>Responsável</th>
                                        <th>Aluno</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (!$clientes): ?>
                                        <tr>
                                            <td colspan="3" class="table-empty">
                                                Nenhum cliente ativo cadastrado ainda.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php
                                        $escopoPost = $_POST['escopo'] ?? 'todos';
                                        $selecionadosPost = $_POST['clientes'] ?? [];
                                        ?>
                                        <?php foreach ($clientes as $c): ?>
                                            <?php
                                            $id = (int)$c['id'];
                                            $checked = ($escopoPost === 'todos')
                                                ? 'checked'
                                                : (in_array((string)$id, (array)$selecionadosPost, true) ? 'checked' : '');
                                            ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="clientes[]"
                                                           value="<?= $id ?>" <?= $checked ?>>
                                                </td>
                                                <td><?= htmlspecialchars($c['nome_tomador'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($c['nome_aluno'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            Gerar notas da competência
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</main>

<script>
(function() {
    var nav = document.querySelector('.topnav[data-section="notas"]');
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

    requestAnimationFrame(function () {
        indicator.classList.add('is-ready');
        positionIndicator(active);
    });

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

