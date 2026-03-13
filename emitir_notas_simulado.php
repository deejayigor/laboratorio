<?php
require __DIR__ . '/config/db.php';

$mysqli = db_connect();
$errors = [];
$success = null;
$resumo = null;

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
        $errors[] = 'Selecione ao menos uma nota para simular a emissão.';
    } else {
        $placeholders = implode(',', array_fill(0, count($idsFiltrados), '?'));
        $tipos = str_repeat('i', count($idsFiltrados));

        $sql = "UPDATE notas
                SET status = 'EMITIDA',
                    mensagem_erro = NULL,
                    numero_nfse = CONCAT('SIM', LPAD(id, 6, '0')),
                    codigo_verificacao = LPAD(id, 8, '0'),
                    chave_acesso = CONCAT('SIMULADO', LPAD(id, 10, '0')),
                    data_emissao = NOW()
                WHERE id IN ($placeholders) AND status = 'PENDENTE'";

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param($tipos, ...$idsFiltrados);
        $stmt->execute();
        $afetadas = $stmt->affected_rows;
        $stmt->close();

        $success = 'Simulação de emissão concluída.';
        $resumo = [
            'selecionadas' => count($idsFiltrados),
            'atualizadas' => $afetadas
        ];
    }
}

// Carrega notas pendentes para exibição
$notas = [];
try {
    $sql = "SELECT n.id, n.competencia, n.valor_servico, n.status,
                   c.nome_aluno, c.nome_tomador, c.cpf_cnpj
            FROM notas n
            JOIN clientes c ON c.id = n.cliente_id
            WHERE n.status = 'PENDENTE'
            ORDER BY n.competencia, c.nome_tomador";
    $res = $mysqli->query($sql);
    if ($res) {
        $notas = $res->fetch_all(MYSQLI_ASSOC);
        $res->close();
    }
} catch (Throwable $e) {
    $notas = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Maple Bear | Emissão simulada de NFS-e</title>
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
            <h3>Emissão simulada de NFS-e</h3>
            <p>
                Esta tela marca as notas pendentes como <strong>EMITIDA</strong> de forma simulada,
                apenas para testar o fluxo interno do sistema. Nenhuma chamada real é feita ao Portal Nacional.
            </p>
        </header>

        <div class="card">
            <div class="card-header card-header-with-actions">
                <div>
                    <h4 class="card-title">Notas pendentes</h4>
                    <p class="card-description">
                        Selecione as notas que você deseja marcar como emitidas na simulação.
                    </p>
                </div>
                <div class="card-actions">
                    <a href="index.php" class="btn">
                        Voltar para o painel
                    </a>
                </div>
            </div>

            <div class="card-body">
                <form method="post" class="form-grid form-group-full">
                    <div class="form-group form-group-full">
                        <div class="card card-table">
                            <div class="card-body">
                                <table class="table">
                                    <thead>
                                    <tr>
                                        <th><input type="checkbox" id="check_all" onclick="toggleAll(this)"></th>
                                        <th>Aluno</th>
                                        <th>Responsável</th>
                                        <th>CPF/CNPJ</th>
                                        <th>Valor</th>
                                        <th>Competência</th>
                                        <th>Status</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (!$notas): ?>
                                        <tr>
                                            <td colspan="7" class="table-empty">
                                                Nenhuma nota pendente encontrada. Gere notas da competência primeiro.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($notas as $nota): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="notas[]"
                                                           value="<?= (int)$nota['id'] ?>" checked>
                                                </td>
                                                <td><?= htmlspecialchars($nota['nome_aluno'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($nota['nome_tomador'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($nota['cpf_cnpj'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td>R$ <?= number_format((float)$nota['valor_servico'], 2, ',', '.') ?></td>
                                                <td><?= htmlspecialchars($nota['competencia'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($nota['status'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
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
                            Marcar selecionadas como emitidas (simulação)
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</main>

<?php
$hasError = !empty($errors);
$hasSuccess = !empty($success);
?>

<?php if ($hasError || $hasSuccess): ?>
<div id="result-modal" class="modal-backdrop">
    <div class="modal">
        <div class="modal-header">
            <?php if ($hasError): ?>
                <div class="modal-icon modal-icon-error">!</div>
                <h4 class="modal-title">Não foi possível concluir</h4>
            <?php else: ?>
                <div class="modal-icon modal-icon-success">✓</div>
                <h4 class="modal-title">Emissão simulada concluída</h4>
            <?php endif; ?>
        </div>
        <div class="modal-body">
            <?php if ($hasError): ?>
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
                <?php if ($resumo): ?>
                    <p>
                        Notas selecionadas: <strong><?= (int)$resumo['selecionadas'] ?></strong><br>
                        Notas efetivamente atualizadas: <strong><?= (int)$resumo['atualizadas'] ?></strong>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn" id="modal-close">
                Fechar
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function toggleAll(source) {
    var checkboxes = document.querySelectorAll('input[name="notas[]"]');
    checkboxes.forEach(function (cb) {
        cb.checked = source.checked;
    });
}

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

(function() {
    var modal = document.getElementById('result-modal');
    if (!modal) return;
    var closeBtn = document.getElementById('modal-close');
    function closeModal() {
        modal.classList.add('is-hidden');
    }
    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            closeModal();
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
})();
</script>
</body>
</html>

