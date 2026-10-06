CREATE DATABASE IF NOT EXISTS gestor_estoque
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gestor_estoque;

CREATE TABLE IF NOT EXISTS empresas (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  razao_social  VARCHAR(150) NOT NULL,
  cnpj          CHAR(14)     NOT NULL UNIQUE,
  nome_fantasia VARCHAR(150) NOT NULL,
  endereco      VARCHAR(255) NOT NULL,
  uf            CHAR(2)      NOT NULL,
  municipio     VARCHAR(120) NOT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS produtos (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  empresa_id INT           NOT NULL,
  nome       VARCHAR(150)  NOT NULL,
  sku        VARCHAR(60)   NOT NULL,
  preco      DECIMAL(12,2) NOT NULL CHECK (preco >= 0),
  quantidade INT           NOT NULL DEFAULT 0 CHECK (quantidade >= 0),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_empresa_sku (empresa_id, sku),
  CONSTRAINT fk_produto_empresa FOREIGN KEY (empresa_id)
    REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB;