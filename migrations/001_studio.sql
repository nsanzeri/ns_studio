-- 001_studio.sql

CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(120) NOT NULL,              -- e.g. backing-track-blueprint
  name VARCHAR(255) NOT NULL,
  kind ENUM('digital','print','other') NOT NULL DEFAULT 'digital',
  file_path VARCHAR(255) NULL,             -- relative to /_storage/downloads/
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_products_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE entitlements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  source ENUM('purchase','subscription','manual_grant') NOT NULL DEFAULT 'purchase',
  status ENUM('active','expired') NOT NULL DEFAULT 'active',
  expires_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ent_user (user_id),
  KEY idx_ent_product (product_id),
  KEY idx_ent_status (status),
  CONSTRAINT fk_ent_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ent_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed your first digital product (Blueprint)
INSERT INTO products (slug, name, kind, file_path)
VALUES ('backing-track-blueprint', 'Backing Track Blueprint', 'digital', 'backing-track-blueprint.pdf');