-- Sprint 2: perfil esportivo do atleta e invalidação de sessões após troca de senha.
-- Somente CREATE / ADD COLUMN (sem DROP).
CREATE TABLE IF NOT EXISTS athlete_profiles (
  user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  phone VARCHAR(15) NULL,
  birth_date DATE NULL,
  city VARCHAR(80) NULL,
  state CHAR(2) NULL,
  goal VARCHAR(500) NULL,
  sports_background VARCHAR(1000) NULL,
  emergency_name VARCHAR(120) NULL,
  emergency_phone VARCHAR(15) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ap_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users ADD COLUMN IF NOT EXISTS session_version INT UNSIGNED NOT NULL DEFAULT 1;
