-- Lista única de objetivos e provas da equipe; os atletas entram e saem de cada um.
-- A tabela athlete_goals (0017) deixa de ser usada e é mantida (sem migração destrutiva).
CREATE TABLE IF NOT EXISTS goals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  created_by BIGINT UNSIGNED NOT NULL,
  kind VARCHAR(10) NOT NULL,
  sport VARCHAR(12) NOT NULL,
  name VARCHAR(150) NOT NULL,
  target_date DATE NULL,
  distance VARCHAR(60) NULL,
  city VARCHAR(100) NULL,
  details VARCHAR(500) NULL,
  removed_at DATETIME NULL,
  removed_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_goals_date (removed_at, target_date),
  CONSTRAINT fk_goals_author FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS goal_participants (
  goal_id BIGINT UNSIGNED NOT NULL,
  athlete_id BIGINT UNSIGNED NOT NULL,
  target VARCHAR(150) NULL,
  result VARCHAR(500) NULL,
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  left_at DATETIME NULL,
  PRIMARY KEY (goal_id, athlete_id),
  KEY idx_gp_athlete (athlete_id),
  CONSTRAINT fk_gp_goal FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE RESTRICT,
  CONSTRAINT fk_gp_athlete FOREIGN KEY (athlete_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
