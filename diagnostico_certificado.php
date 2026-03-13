<?php
/**
 * Diagnóstico de Certificado Digital – nfse_escola (Maple Bear)
 *
 * Verifica se o certificado A1 está acessível, válido e se a assinatura XML funciona.
 * Usa config_nfse_escola.php e classes/XMLSigner.php (tudo dentro de nfse_escola).
 */

$config = require __DIR__ . '/config_nfse_escola.php';
require_once __DIR__ . '/classes/XMLSigner.php';

$cert_path = $config['nacional']['certificado_path'];
$cert_senha = $config['nacional']['certificado_senha'];

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico de Certificado – NFSe Escola</title>
    <link rel="stylesheet" href="public/styles.css">
    <style>
        .diag-section { background: var(--card-bg, #fff); padding: 1.25rem; margin: 1rem 0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .diag-ok { color: #15803d; font-weight: 600; }
        .diag-erro { color: #b91c1c; font-weight: 600; }
        .diag-aviso { color: #b45309; font-weight: 600; }
        .diag-info { color: #0369a1; }
        .diag-section h2 { margin: 0 0 0.75rem 0; font-size: 1.1rem; border-bottom: 2px solid #c2410c; padding-bottom: 0.35rem; }
        .diag-section h3 { color: #555; margin: 1rem 0 0.5rem 0; font-size: 0.95rem; }
        .diag-section pre { background: #f1f5f9; padding: 0.75rem; border-radius: 4px; overflow-x: auto; font-size: 0.85rem; }
        .diag-section table { width: 100%; border-collapse: collapse; margin: 0.5rem 0; font-size: 0.9rem; }
        .diag-section th, .diag-section td { padding: 0.5rem 0.75rem; border: 1px solid #e2e8f0; text-align: left; }
        .diag-section th { background: #c2410c; color: #fff; }
        .diag-section ul { margin: 0.5rem 0; padding-left: 1.25rem; }
        .diag-nav { margin-bottom: 1rem; }
        .diag-nav a { color: #c2410c; text-decoration: none; }
        .diag-nav a:hover { text-decoration: underline; }
    </style>
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
        <div class="diag-nav"><a href="index.php">← Voltar ao painel</a></div>
        <h1 style="margin-bottom: 0.5rem;">Diagnóstico de Certificado Digital</h1>
        <p style="color: #64748b; margin-bottom: 1rem;">Verificação do certificado A1 usado pela NFSe Escola (Maple Bear Botucatu).</p>

<?php

// 1. Arquivo do certificado
echo '<div class="diag-section">';
echo '<h2>1. Arquivo do certificado</h2>';

if (!file_exists($cert_path)) {
    echo '<p class="diag-erro">Arquivo não encontrado: ' . htmlspecialchars($cert_path) . '</p>';
    echo '</div></main></body></html>';
    exit;
}

echo '<p class="diag-ok">Arquivo encontrado.</p>';
echo '<p>Tamanho: ' . number_format(filesize($cert_path)) . ' bytes</p>';
echo '<p>Última modificação: ' . date('d/m/Y H:i:s', filemtime($cert_path)) . '</p>';
echo '</div>';

// 2. Carregamento e validação
echo '<div class="diag-section">';
echo '<h2>2. Carregamento e validação do certificado</h2>';

$pfx_data = file_get_contents($cert_path);
$cert_data = [];

if (!openssl_pkcs12_read($pfx_data, $cert_data, $cert_senha)) {
    echo '<p class="diag-erro">Erro ao ler certificado PFX. Verifique a senha.</p>';
    echo '<p>OpenSSL: ' . htmlspecialchars(openssl_error_string() ?: '—') . '</p>';
    echo '</div></main></body></html>';
    exit;
}

echo '<p class="diag-ok">Certificado PFX carregado com sucesso.</p>';

echo '<h3>Componentes do PFX</h3>';
echo '<ul>';
echo '<li>' . (isset($cert_data['cert']) ? 'Certificado presente' : 'Certificado ausente') . '</li>';
echo '<li>' . (isset($cert_data['pkey']) ? 'Chave privada presente' : 'Chave privada ausente') . '</li>';
echo '<li>' . (isset($cert_data['extracerts']) && !empty($cert_data['extracerts']) ? 'Certificados intermediários: ' . count($cert_data['extracerts']) : 'Sem intermediários') . '</li>';
echo '</ul>';

$cert_info = openssl_x509_parse($cert_data['cert']);

echo '<h3>Dados do certificado</h3>';
echo '<table>';
echo '<tr><th>Campo</th><th>Valor</th></tr>';
echo '<tr><td>CN (Common Name)</td><td>' . htmlspecialchars($cert_info['subject']['CN'] ?? '—') . '</td></tr>';
echo '<tr><td>Serial Number (CNPJ)</td><td>' . htmlspecialchars($cert_info['subject']['serialNumber'] ?? '—') . '</td></tr>';
echo '<tr><td>Emissor</td><td>' . htmlspecialchars($cert_info['issuer']['CN'] ?? '—') . '</td></tr>';
echo '<tr><td>Válido de</td><td>' . date('d/m/Y H:i:s', $cert_info['validFrom_time_t']) . '</td></tr>';
echo '<tr><td>Válido até</td><td>' . date('d/m/Y H:i:s', $cert_info['validTo_time_t']) . '</td></tr>';

$dias_restantes = floor(($cert_info['validTo_time_t'] - time()) / 86400);
$status_validade = ($dias_restantes > 0) ? '<span class="diag-ok">Válido</span>' : '<span class="diag-erro">Vencido</span>';
echo '<tr><td>Status</td><td>' . $status_validade . ' (' . $dias_restantes . ' dias restantes)</td></tr>';
echo '</table>';

if (isset($cert_info['extensions']['crlDistributionPoints'])) {
    echo '<p class="diag-info">CRL Distribution Points encontrado.</p>';
} else {
    echo '<p class="diag-aviso">CRL Distribution Points não encontrado.</p>';
}
if (isset($cert_info['extensions']['authorityInfoAccess'])) {
    echo '<p class="diag-info">Authority Info Access (OCSP) encontrado.</p>';
} else {
    echo '<p class="diag-aviso">Authority Info Access não encontrado.</p>';
}

echo '</div>';

// 3. Revogação (resumo)
echo '<div class="diag-section">';
echo '<h2>3. Revogação (OCSP/CRL)</h2>';
echo '<p class="diag-info">A verificação de revogação é feita pelo servidor do Portal Nacional na emissão. Este diagnóstico apenas confere se o certificado carrega e assina corretamente.</p>';
echo '</div>';

// 4. Assinatura XML
echo '<div class="diag-section">';
echo '<h2>4. Assinatura XML (XMLSigner)</h2>';

try {
    $signer = new XMLSigner($cert_path, $cert_senha);
    $cert_info_obj = $signer->getCertificadoInfo();

    if ($cert_info_obj) {
        echo '<p class="diag-ok">XMLSigner carregou o certificado.</p>';
        echo '<table>';
        foreach ($cert_info_obj as $key => $value) {
            echo '<tr><td><strong>' . htmlspecialchars($key) . '</strong></td><td>' . htmlspecialchars($value) . '</td></tr>';
        }
        echo '</table>';
    }

    $xml_teste = '<?xml version="1.0" encoding="utf-8"?><DPS xmlns="http://www.sped.fazenda.gov.br/nfse"><infDPS Id="teste123"><xDesc>Teste diagnóstico</xDesc></infDPS></DPS>';
    $xml_assinado = $signer->assinarXML($xml_teste, 'infDPS');

    echo '<p class="diag-ok">Assinatura XML realizada com sucesso.</p>';
    echo '<p>Tamanho do XML assinado: ' . strlen($xml_assinado) . ' bytes.</p>';

    if (strpos($xml_assinado, 'X509Certificate') !== false) {
        echo '<p class="diag-ok">Certificado incluído na assinatura XML.</p>';
    } else {
        echo '<p class="diag-erro">Certificado não encontrado na assinatura XML.</p>';
    }

} catch (Exception $e) {
    echo '<p class="diag-erro">Erro ao testar assinatura: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '</div>';

// 5. Config em uso
echo '<div class="diag-section">';
echo '<h2>5. Configuração em uso</h2>';
echo '<p><strong>Ambiente:</strong> ' . htmlspecialchars($config['nacional']['ambiente']) . '</p>';
echo '<p><strong>Provedor:</strong> ' . htmlspecialchars($config['provedor_ativo']) . '</p>';
echo '</div>';

?>

    </main>
</body>
</html>
