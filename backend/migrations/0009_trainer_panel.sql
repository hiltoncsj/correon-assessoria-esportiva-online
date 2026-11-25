-- Sprint 4: capacidade, exceções, disponibilidade e marcação de novidades do treinador. Somente CREATE.
CREATE TABLE IF NOT EXISTS trainer_settings (
  trainer_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  online_limit SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  presencial_limit SMALLINT UNSIGNED NULL,          -- sem valor definido até o Personal Trainer (Sprint 7)
  news_seen_at DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ts_t FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS capacity_exceptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  trainer_id BIGINT UNSIGNED NOT NULL,
  athlete_id BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(300) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_ce_t (trainer_id, created_at),
  CONSTRAINT fk_ce_t FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ce_a FOREIGN KEY (athlete_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trainer_availability (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  trainer_id BIGINT UNSIGNED NOT NULL,
  weekday TINYINT UNSIGNED NOT NULL,                -- 1 = segunda ... 7 = domingo
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  mode ENUM('online','presencial') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_av_t (trainer_id, weekday, start_time),
  CONSTRAINT fk_av_t FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
