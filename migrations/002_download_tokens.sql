-- 002_download_tokens.sql
-- Adds token-gated downloads for digital products

CREATE TABLE download_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  token CHAR(64) NOT NULL,
  checkout_session_id VARCHAR(255) NOT NULL,
  purchaser_email VARCHAR(255) NULL,
  product_key VARCHAR(64) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  uses_remaining INT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_token (token),
  UNIQUE KEY uniq_session_product (checkout_session_id, product_key),
  KEY idx_expires_at (expires_at),
  KEY idx_session (checkout_session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
