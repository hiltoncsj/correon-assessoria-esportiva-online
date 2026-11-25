-- Sprint 3 (ajuste 3): frequência cardíaca máxima por atleta (base do cálculo das 7 zonas padrão).
-- Dado definido pelo treinador; as faixas em bpm continuam em athlete_hr_zones e podem ser ajustadas manualmente.
CREATE TABLE IF NOT EXISTS athlete_hr_settings (
  athlete_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  max_hr SMALLINT UNSIGNED NOT NULL,
  updated_by BIGINT UNSIGNED NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_hs_a FOREIGN KEY (athlete_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_hs_u FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
