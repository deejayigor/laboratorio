<?php
/**
 * Configuração NFS-e – Maple Bear Botucatu (nfse_escola)
 *
 * Usado pelo diagnóstico do certificado e, futuramente, pela emissão real.
 * Dados da escola podem ser sobrescritos pelos cadastrados em empresa (banco).
 */

$base = __DIR__;

return [
    'provedor_ativo' => 'nacional',

    'nacional' => [
        'ambiente' => 'homologacao', // 'homologacao' ou 'producao'

        'endpoint_homologacao' => 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional',
        'endpoint_producao' => 'https://sefin.nfse.gov.br/SefinNacional',

        'endpoint_cnc_homologacao' => 'https://adn.producaorestrita.nfse.gov.br/cnc/consulta',
        'endpoint_cnc_producao' => 'https://adn.nfse.gov.br/cnc/consulta',

        // Certificado A1 (Maple Bear) – dentro de nfse_escola/config/
        'certificado_path' => $base . '/config/SARAH 24706159 validade 21 03 25 a 21 03 26 senha 123456.pfx',
        'certificado_senha' => '123456',

        'namespace_dps' => 'http://www.sped.fazenda.gov.br/nfse',
        'namespace_xmldsig' => 'http://www.w3.org/2000/09/xmldsig#',

        'timeout' => 30,
        'tipo' => 'REST',
        'formato' => 'JSON',
        'versao_dps' => '1.00',

        'ssl_verify_peer' => true,
        'ssl_verify_host' => true,
        'ssl_cainfo' => null, // opcional: caminho para cacert.pem se necessário
    ],

    // Dados padrão do prestador (Maple Bear Botucatu) – referência NF_MB.pdf
    'prestador' => [
        'cnpj' => '31653761000176',
        'inscricao_municipal' => '',
        'incluir_im' => false,
        'razao_social' => 'MB BOTUCATU INSTITUICAO DE ENSINO LTDA',
        'nome_fantasia' => 'Maple Bear Botucatu',

        'logradouro' => 'Doutor Costa Leite',
        'numero' => '2479',
        'complemento' => '',
        'bairro' => 'Vila Nogueira',
        'codigo_municipio' => '3507506',
        'uf' => 'SP',
        'cep' => '18606820',

        'telefone' => '',
        'email' => '',

        // Escola é optante do Simples (ME/EPP)
        'optante_simples_nacional' => true,
        'regime_especial_tributacao' => 0,
        // Regime de apuração dos tributos do SN (TSRegimeApuracaoSimpNac):
        // 1 – Tributos federais e municipal pelo SN;
        // 2 – Federais pelo SN e ISSQN por fora;
        // 3 – Federais e municipal por fora do SN.
        // Até orientação da contabilidade, usaremos '1'.
        'regime_apuracao_sn' => '1',

        'serie_dps' => '001',
        'versao_aplicativo' => 'NFSE_ESCOLA_MB_v1.0',
    ],

    'servico' => [
        'item_lista_servico' => '08.01',
        'codigo_tributacao_nacional' => '080101',
        'codigo_tributacao_municipal' => '001',
        'codigo_nbs' => '122012000',
        'descricao_padrao' => 'Mensalidade escolar referente ao mês {competencia}',
        'aliquota_iss' => 2.06,
        'percentual_tributos_simples' => 15.51,
    ],

    'debug' => [
        'ativo' => true,
        'log_path' => $base . '/data/nfse_escola.log',
        'salvar_xml' => true,
        'xml_path' => $base . '/data/xml/',
    ],
];
