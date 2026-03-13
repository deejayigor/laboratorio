<?php
require __DIR__ . '/config/db.php';

$mysqli = db_connect();

// Estatísticas simples
$totalClientes = 0;
$totalPendentes = 0;
$totalEmitidasMes = 0;
$taxaEmissaoMes = null;

try {
    $res = $mysqli->query('SELECT COUNT(*) AS total FROM clientes WHERE ativo = 1');
    if ($res) {
        $row = $res->fetch_assoc();
        $totalClientes = (int)($row['total'] ?? 0);
        $res->close();
    }

    $totalMovimentoMes = $totalPendentes + $totalEmitidasMes;
    if ($totalMovimentoMes > 0) {
        $taxaEmissaoMes = (int)round(($totalEmitidasMes / $totalMovimentoMes) * 100);
    }

    $res = $mysqli->query("SELECT COUNT(*) AS total FROM notas WHERE status = 'PENDENTE'");
    if ($res) {
        $row = $res->fetch_assoc();
        $totalPendentes = (int)($row['total'] ?? 0);
        $res->close();
    }

    $competenciaAtual = date('m/Y');
    $stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM notas WHERE status = 'EMITIDA' AND competencia = ?");
    if ($stmt) {
        $stmt->bind_param('s', $competenciaAtual);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            $row = $res->fetch_assoc();
            $totalEmitidasMes = (int)($row['total'] ?? 0);
            $res->close();
        }
        $stmt->close();
    }
} catch (Throwable $e) {
    // Em caso de erro, mantemos os valores padrão (0) e não quebramos a página.
}

// Lista de notas (últimas 20) com dados do cliente
$notas = [];
try {
    $sql = "SELECT n.id, n.competencia, n.valor_servico, n.status,
                   c.nome_aluno, c.nome_tomador, c.cpf_cnpj
            FROM notas n
            JOIN clientes c ON c.id = n.cliente_id
            ORDER BY n.criado_em DESC
            LIMIT 20";
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
    <title>Maple Bear | NFSe Financeiro</title>
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
        <section class="hero">
            <div class="hero-text">
                <h1>Always learn. Always organized.</h1>
                <p>
                    Centralize a emissão de Notas Fiscais de Serviços da escola
                    com a mesma excelência acadêmica da Maple Bear.
                </p>
            </div>
            <div class="hero-highlight">
                <span class="hero-pill">Painel de emissão</span>
                <h2>Notas prontas para emitir</h2>
                <p class="hero-sub">
                    Gere as notas da competência e acompanhe a fila de emissão de NFS-e da escola.
                </p>
                <div class="hero-actions">
                    <a href="gerar_notas.php" class="btn btn-primary">
                        Gerar notas da competência
                    </a>
                    <a href="emitir_notas_simulado.php" class="btn">
                        Emissão simulada (interno)
                    </a>
                    <a href="emitir_notas_homologacao.php" class="btn">
                        Emissão em homologação
                    </a>
                </div>
                <p class="hero-hint">
                    Use a <strong>emissão simulada</strong> para testar o fluxo apenas dentro do sistema
                    e a <strong>emissão em homologação</strong> para enviar de fato as notas ao Portal Nacional
                    no ambiente de testes. A ida para produção exigirá apenas trocar o ambiente na configuração.
                </p>
            </div>
        </section>

        <section class="section">
            <header class="section-header">
                <h3>Visão geral de faturamento</h3>
                <p>Painel rápido das NFS-e da unidade Maple Bear.</p>
            </header>

            <div class="section-grid">
                <div class="card card-summary">
                    <div class="card-header">
                        <div>
                            <h4 class="card-title">Painel da competência</h4>
                            <p class="card-description">
                                Fotografia rápida do movimento de NFS-e desta competência.
                            </p>
                        </div>
                    </div>
                    <div class="card-body card-body-summary">
                        <div class="summary-layout">
                            <div class="summary-hero">
                                <span class="summary-label">Competência atual</span>
                                <div class="summary-competencia">
                                    <?= htmlspecialchars($competenciaAtual, ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <p class="summary-copy">
                                    <?= $totalPendentes > 0
                                        ? 'Há notas prontas para emissão. Priorize limpar a fila antes do próximo ciclo.'
                                        : 'Nenhuma nota pendente nesta competência — fila pronta para o próximo ciclo.'; ?>
                                </p>
                                <?php if ($taxaEmissaoMes !== null): ?>
                                    <div class="summary-metric-pill">
                                        <span class="summary-metric-number"><?= $taxaEmissaoMes ?>%</span>
                                        <span class="summary-metric-label">das notas do mês já emitidas</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="summary-metrics">
                                <ul class="stat-list">
                                    <li class="stat-item">
                                        <span class="stat-label">Clientes ativos</span>
                                        <span class="stat-value"><?= (int)$totalClientes ?></span>
                                    </li>
                                    <li class="stat-item">
                                        <span class="stat-label">Notas pendentes</span>
                                        <span class="stat-value"><?= (int)$totalPendentes ?></span>
                                        <?php if ($totalPendentes > 0): ?>
                                            <span class="stat-tag stat-tag-warning">há notas aguardando emissão</span>
                                        <?php else: ?>
                                            <span class="stat-tag stat-tag-ok">fila limpa</span>
                                        <?php endif; ?>
                                    </li>
                                    <li class="stat-item">
                                        <span class="stat-label">Emitidas no mês</span>
                                        <span class="stat-value"><?= (int)$totalEmitidasMes ?></span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <small>
                            Use a <strong>emissão em homologação</strong> para testar a comunicação real com o Portal Nacional
                            antes de ativar o ambiente de produção.
                        </small>
                    </div>
                </div>

                <div class="card card-table">
                    <div class="card-header card-header-with-actions">
                        <div>
                            <h4 class="card-title">Fila de NFS-e</h4>
                            <p class="card-description">
                                Últimas notas geradas no sistema e seus status atuais.
                            </p>
                        </div>
                        <div class="card-actions">
                            <div class="card-filter-group">
                                <label class="card-filter-label" for="filtro_competencia">Competência</label>
                                <select id="filtro_competencia" class="input input-sm" disabled>
                                    <option>Em breve</option>
                                </select>
                            </div>
                            <div class="card-filter-group">
                                <label class="card-filter-label" for="filtro_busca">Buscar</label>
                                <input id="filtro_busca" class="input input-sm" type="text" placeholder="Aluno ou responsável" disabled>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Selecionar</th>
                                    <th>Aluno</th>
                                    <th>Responsável (tomador)</th>
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
                                            Nenhuma NFS cadastrada ainda.
                                            Assim que você gerar as notas da competência e cadastrá-las na fila,
                                            elas aparecerão aqui para emissão em lote ou individual.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($notas as $nota): ?>
                                        <tr>
                                            <td><input type="checkbox" disabled></td>
                                            <td><?= htmlspecialchars($nota['nome_aluno'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($nota['nome_tomador'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($nota['cpf_cnpj'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>R$ <?= number_format((float)$nota['valor_servico'], 2, ',', '.') ?></td>
                                        <td><?= htmlspecialchars($nota['competencia'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <span class="status-pill status-<?= strtolower($nota['status'] ?? '') ?>">
                                                <?= htmlspecialchars($nota['status'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
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

