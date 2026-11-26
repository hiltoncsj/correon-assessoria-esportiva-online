-- Objetivos e provas dos atletas. Nada é apagado: o que não vai acontecer fica como cancelado.
CREATE TABLE IF NOT EXISTS athlete_goals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  athlete_id BIGINT UNSIGNED NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  kind VARCHAR(10) NOT NULL,
  sport VARCHAR(12) NOT NULL,
  name VARCHAR(150) NOT NULL,
  target_date DATE NULL,
  distance VARCHAR(60) NULL,
  city VARCHAR(100) NULL,
  target VARCHAR(150) NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'planned',
  result VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_goals_athlete (athlete_id, target_date),
  CONSTRAINT fk_goal_athlete FOREIGN KEY (athlete_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_goal_author FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
