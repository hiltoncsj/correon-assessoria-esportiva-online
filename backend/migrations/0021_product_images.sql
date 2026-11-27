-- Várias fotos por produto. products.image_id passa a guardar a capa (a primeira foto).
CREATE TABLE IF NOT EXISTS product_images (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  media_id BIGINT UNSIGNED NOT NULL,
  UNIQUE KEY uq_pi_media (media_id),
  KEY idx_pi_product (product_id, id),
  CONSTRAINT fk_pi_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO product_images (product_id, media_id)
  SELECT id, image_id FROM products WHERE image_id IS NOT NULL;
