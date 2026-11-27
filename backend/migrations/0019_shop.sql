-- Loja de uniformes. Dinheiro em centavos; nada é apagado.
CREATE TABLE IF NOT EXISTS products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(1000) NULL,
  price_cents INT UNSIGNED NOT NULL,
  image_id BIGINT UNSIGNED NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_variants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  size VARCHAR(20) NOT NULL,
  stock INT UNSIGNED NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_variant (product_id, size),
  CONSTRAINT fk_variant_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  athlete_id BIGINT UNSIGNED NOT NULL,
  payment_method VARCHAR(12) NOT NULL,
  total_cents INT UNSIGNED NOT NULL,
  payment_link VARCHAR(500) NULL,
  paid_at DATETIME NULL,
  paid_by BIGINT UNSIGNED NULL,
  delivered_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  cancelled_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_orders_athlete (athlete_id),
  KEY idx_orders_created (created_at),
  CONSTRAINT fk_order_athlete FOREIGN KEY (athlete_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  variant_id BIGINT UNSIGNED NOT NULL,
  product_name VARCHAR(120) NOT NULL,
  size VARCHAR(20) NOT NULL,
  unit_cents INT UNSIGNED NOT NULL,
  qty INT UNSIGNED NOT NULL,
  CONSTRAINT fk_item_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
  CONSTRAINT fk_item_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Uma única linha (id = 1) com os dados de pagamento e entrega informados pela administração.
CREATE TABLE IF NOT EXISTS shop_settings (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  pix_key VARCHAR(140) NULL,
  pix_holder VARCHAR(120) NULL,
  card_link VARCHAR(500) NULL,
  allow_on_delivery TINYINT(1) NOT NULL DEFAULT 1,
  instructions VARCHAR(1000) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
