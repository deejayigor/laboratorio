<?php
/**
 * teste_conexao_api.php
 *
 * Teste simples de conexão mTLS com o endpoint do Portal Nacional (homologação),
 * usando a mesma configuração e certificado do restante do sistema.
 */

require __DIR__ . '/config_nfse_escola.php';

$config = require __DIR__ . '/config_nfse_escola.php';
require_once __DIR__ . '/classes/HTTPClient.php';

$cliente = new HTTPClient($config);
$resultado = $cliente->testarConexao();

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Teste de conexão API NFS-e Nacional</title>
    <link rel="stylesheet" href="public/styles.css">
</head>
<body class="page">
<header class="topbar">
    <div class="topbar-inner container">
        <a href="index.php" class="brand">
            <span class="brand-mark">MB</span>
            <span class="brand-text">Maple Bear · Financeiro NFSe</span>
        </a>
    </div>
</header>

<main class="container" style="padding-top: 1.5rem;">
    <h1>Teste de conexão com a API NFS-e Nacional</h1>
    <p style="color:#64748b;margin-bottom:1rem;">
        Verifica se é possível abrir uma conexão HTTPS mTLS com o endpoint
        <code><?= htmlspecialchars($resultado['endpoint']) ?></code>
        usando o certificado configurado.
    </p>

    <div class="card">
        <div class="card-body">
            <p><strong>Endpoint:</strong> <?= htmlspecialchars($resultado['endpoint']) ?></p>
            <p><strong>HTTP code:</strong> <?= (int)$resultado['http_code'] ?></p>
            <p><strong>Sucesso:</strong>
                <?= $resultado['sucesso'] ? '<span style="color:#16a34a;font-weight:600;">SIM</span>' : '<span style="color:#b91c1c;font-weight:600;">NÃO</span>' ?>
            </p>
            <?php if (!empty($resultado['erro'])): ?>
                <p><strong>Erro cURL:</strong> <?= htmlspecialchars($resultado['erro']) ?></p>
            <?php endif; ?>
            <?php if (!empty($resultado['resumo'])): ?>
                <p><strong>Resumo da resposta:</strong></p>
                <pre style="background:#f1f5f9;padding:0.75rem;border-radius:4px;overflow-x:auto;font-size:0.85rem;"><?= htmlspecialchars($resultado['resumo']) ?></pre>
            <?php endif; ?>
        </div>
    </div>

    <p style="margin-top:1rem;">
        Voltar para o <a href="index.php">painel</a>.
    </p>
</main>
</body>
</html>

