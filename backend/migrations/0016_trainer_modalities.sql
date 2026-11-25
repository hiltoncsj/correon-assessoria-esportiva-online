-- Modalidades que cada treinador atende. Preenche com as modalidades em que já há atletas ativos.
CREATE TABLE IF NOT EXISTS trainer_modalities (
  trainer_id BIGINT UNSIGNED NOT NULL,
  modality_id TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (trainer_id, modality_id),
  CONSTRAINT fk_tm_t FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_tm_m FOREIGN KEY (modality_id) REFERENCES modalities(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO trainer_modalities (trainer_id, modality_id)
  SELECT DISTINCT trainer_id, modality_id FROM trainer_athletes WHERE status = 'active';
