-- Sprint 3: modalidades, vínculo treinador-atleta, treinos planejados (versionados) e treinos realizados.
-- Somente CREATE/INSERT IGNORE (sem DROP).
CREATE TABLE IF NOT EXISTS modalities (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(30) NOT NULL,
  name VARCHAR(60) NOT NULL,
  UNIQUE KEY uq_mod_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO modalities (slug, name) VALUES ('corrida','Corrida'), ('triathlon','Triathlon'), ('musculacao','Musculação');

-- Vínculo por modalidade: um atleta pode ter mais de um treinador (um por modalidade ou mais).
CREATE TABLE IF NOT EXISTS trainer_athletes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  trainer_id BIGINT UNSIGNED NOT NULL,
  athlete_id BIGINT UNSIGNED NOT NULL,
  modality_id TINYINT UNSIGNED NOT NULL,
  status ENUM('active','ended') NOT NULL DEFAULT 'active',
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at DATETIME NULL,
  UNIQUE KEY uq_ta (trainer_id, athlete_id, modality_id),
  KEY ix_ta_athlete (athlete_id, status),
  CONSTRAINT fk_ta_trainer FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ta_athlete FOREIGN KEY (athlete_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ta_mod FOREIGN KEY (modality_id) REFERENCES modalities(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Treino PLANEJADO (prescrição).
CREATE TABLE IF NOT EXISTS workouts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  athlete_id BIGINT UNSIGNED NOT NULL,
  modality_id TINYINT UNSIGNED NOT NULL,
  discipline ENUM('corrida','ciclismo','natacao','musculacao') NOT NULL,
  scheduled_date DATE NOT NULL,
  title VARCHAR(120) NOT NULL,
  notes VARCHAR(1000) NULL,
  status ENUM('draft','published','cancelled') NOT NULL DEFAULT 'draft',
  version INT UNSIGNED NOT NULL DEFAULT 1,
  published_at DATETIME NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  updated_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_w_athlete_date (athlete_id, scheduled_date),
  KEY ix_w_mod (modality_id),
  CONSTRAINT fk_w_athlete FOREIGN KEY (athlete_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_w_mod FOREIGN KEY (modality_id) REFERENCES modalities(id) ON DELETE RESTRICT,
  CONSTRAINT fk_w_cb FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_w_ub FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS workout_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  workout_id BIGINT UNSIGNED NOT NULL,
  position SMALLINT UNSIGNED NOT NULL,
  label VARCHAR(120) NOT NULL,          -- etapa (resistência) ou exercício (musculação)
  sets SMALLINT UNSIGNED NULL,
  reps VARCHAR(20) NULL,
  load_text VARCHAR(30) NULL,
  rest_seconds SMALLINT UNSIGNED NULL,
  duration_minutes SMALLINT UNSIGNED NULL,
  distance_km DECIMAL(7,2) NULL,
  target VARCHAR(120) NULL,             -- ritmo, zona, intensidade
  notes VARCHAR(300) NULL,
  KEY ix_wi_workout (workout_id, position),
  CONSTRAINT fk_wi_w FOREIGN KEY (workout_id) REFERENCES workouts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Histórico: foto completa (JSON) a cada criação/alteração/publicação.
CREATE TABLE IF NOT EXISTS workout_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  workout_id BIGINT UNSIGNED NOT NULL,
  version INT UNSIGNED NOT NULL,
  change_note VARCHAR(30) NOT NULL,
  snapshot MEDIUMTEXT NOT NULL,
  changed_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wv (workout_id, version),
  CONSTRAINT fk_wv_w FOREIGN KEY (workout_id) REFERENCES workouts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wv_u FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Treino REALIZADO (separado do planejado; a prescrição nunca o sobrescreve).
CREATE TABLE IF NOT EXISTS workout_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  workout_id BIGINT UNSIGNED NOT NULL,
  athlete_id BIGINT UNSIGNED NOT NULL,
  outcome ENUM('completed','partial','skipped') NOT NULL,
  performed_on DATE NOT NULL,
  duration_minutes SMALLINT UNSIGNED NULL,
  distance_km DECIMAL(7,2) NULL,
  effort TINYINT UNSIGNED NULL,
  notes VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wl_workout (workout_id),
  KEY ix_wl_athlete (athlete_id, performed_on),
  CONSTRAINT fk_wl_w FOREIGN KEY (workout_id) REFERENCES workouts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wl_a FOREIGN KEY (athlete_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS workout_log_revisions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  log_id BIGINT UNSIGNED NOT NULL,
  snapshot TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_wlr (log_id),
  CONSTRAINT fk_wlr_l FOREIGN KEY (log_id) REFERENCES workout_logs(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
