-- Sprint 3 (ajuste): modelos de treino (Treino 1, 2, 3...) aplicáveis a vários atletas.
-- Cada treino aplicado é uma CÓPIA independente do modelo (editar o modelo não altera treinos já entregues).
CREATE TABLE IF NOT EXISTS workout_templates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  trainer_id BIGINT UNSIGNED NOT NULL,
  modality_id TINYINT UNSIGNED NOT NULL,
  discipline ENUM('corrida','ciclismo','natacao','musculacao') NOT NULL,
  name VARCHAR(120) NOT NULL,
  notes VARCHAR(1000) NULL,
  archived_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_wt_trainer (trainer_id, modality_id, archived_at),
  CONSTRAINT fk_wt_trainer FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wt_mod FOREIGN KEY (modality_id) REFERENCES modalities(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS workout_template_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  template_id BIGINT UNSIGNED NOT NULL,
  position SMALLINT UNSIGNED NOT NULL,
  label VARCHAR(120) NOT NULL,
  sets SMALLINT UNSIGNED NULL,
  reps VARCHAR(20) NULL,
  load_text VARCHAR(30) NULL,
  rest_seconds SMALLINT UNSIGNED NULL,
  duration_minutes SMALLINT UNSIGNED NULL,
  distance_km DECIMAL(7,2) NULL,
  target VARCHAR(120) NULL,
  notes VARCHAR(300) NULL,
  KEY ix_wti_template (template_id, position),
  CONSTRAINT fk_wti_t FOREIGN KEY (template_id) REFERENCES workout_templates(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE workouts ADD COLUMN IF NOT EXISTS source_template_id BIGINT UNSIGNED NULL;
ALTER TABLE workouts ADD KEY IF NOT EXISTS ix_w_template (source_template_id, athlete_id, scheduled_date);
