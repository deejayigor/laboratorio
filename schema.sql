-- Banco: nfse_maplebear
-- Este script cria o banco (caso não exista) e seleciona para uso.
CREATE DATABASE IF NOT EXISTS nfse_maplebear
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE nfse_maplebear;

-- Tabela de configuração da empresa (escola)
CREATE TABLE IF NOT EXISTS empresa (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    razao_social VARCHAR(255) NOT NULL,
    nome_fantasia VARCHAR(255) NULL,
    cnpj CHAR(14) NOT NULL,
    inscricao_municipal VARCHAR(30) NULL,
    logradouro VARCHAR(255) NOT NULL,
    numero VARCHAR(30) NOT NULL,
    complemento VARCHAR(100) NULL,
    bairro VARCHAR(150) NOT NULL,
    cidade VARCHAR(150) NOT NULL,
    uf CHAR(2) NOT NULL,
    cep CHAR(8) NOT NULL,
    telefone VARCHAR(30) NULL,
    email_financeiro VARCHAR(150) NULL,
    codigo_municipio_ibge CHAR(7) NOT NULL,
    item_lista_servico VARCHAR(20) NOT NULL,
    codigo_tributacao_municipal VARCHAR(30) NOT NULL,
    codigo_tributacao_nacional VARCHAR(30) NOT NULL,
    codigo_nbs VARCHAR(30) NULL,
    aliquota_iss DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    descricao_servico_padrao VARCHAR(500) NOT NULL,
    certificado_caminho VARCHAR(255) NOT NULL,
    certificado_senha VARCHAR(255) NOT NULL,
    smtp_host VARCHAR(150) NULL,
    smtp_porta INT NULL,
    smtp_usuario VARCHAR(150) NULL,
    smtp_senha VARCHAR(255) NULL,
    smtp_from_email VARCHAR(150) NULL,
    smtp_from_nome VARCHAR(150) NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de clientes (tomadores)
CREATE TABLE IF NOT EXISTS clientes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome_tomador VARCHAR(255) NOT NULL,
    nome_aluno VARCHAR(255) NULL,
    cpf_cnpj CHAR(14) NOT NULL,
    email VARCHAR(150) NOT NULL,
    logradouro VARCHAR(255) NOT NULL,
    numero VARCHAR(30) NOT NULL,
    complemento VARCHAR(100) NULL,
    bairro VARCHAR(150) NOT NULL,
    cidade VARCHAR(150) NOT NULL,
    uf CHAR(2) NOT NULL,
    cep CHAR(8) NOT NULL,
    telefone VARCHAR(30) NULL,
    id_interno VARCHAR(100) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_cpf_cnpj (cpf_cnpj),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de notas fiscais emitidas
CREATE TABLE IF NOT EXISTS notas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cliente_id INT UNSIGNED NOT NULL,
    competencia VARCHAR(7) NOT NULL, -- ex: 03/2026
    valor_servico DECIMAL(12,2) NOT NULL,
    descricao_servico VARCHAR(500) NOT NULL,
    status ENUM('PENDENTE','EMITIDA','ERRO','CANCELADA') NOT NULL DEFAULT 'PENDENTE',
    mensagem_erro TEXT NULL,
    -- Dados retornados pela NFSe
    numero_nfse VARCHAR(30) NULL,
    codigo_verificacao VARCHAR(50) NULL,
    chave_acesso VARCHAR(60) NULL,
    data_emissao DATETIME NULL,
    url_danfe VARCHAR(500) NULL,
    caminho_pdf_local VARCHAR(255) NULL,
    -- Controle interno
    dps_numero BIGINT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_cliente (cliente_id),
    INDEX idx_competencia (competencia),
    CONSTRAINT fk_notas_clientes FOREIGN KEY (cliente_id)
        REFERENCES clientes (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para controle de PDFs pendentes (similar à nf_pdf_pendentes)
CREATE TABLE IF NOT EXISTS pdf_pendentes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nota_id INT UNSIGNED NOT NULL,
    chave_acesso VARCHAR(60) NOT NULL,
    tentativas INT NOT NULL DEFAULT 0,
    ultima_tentativa DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_nota (nota_id),
    CONSTRAINT fk_pdf_notas FOREIGN KEY (nota_id)
        REFERENCES notas (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

