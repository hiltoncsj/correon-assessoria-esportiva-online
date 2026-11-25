-- Sprint 7: Personal Trainer (presencial), produto independente dos planos digitais. Somente CREATE.
CREATE TABLE IF NOT EXISTS pt_packages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  athlete_id BIGINT UNSIGNED NOT NULL,
  trainer_id BIGINT UNSIGNED NOT NULL,
  format ENUM('pacote','mensal','avulsa') NOT NULL,
  sessions_total SMALLINT UNSIGNED NULL,           -- pacote / avulsa
  weekly_frequency TINYINT UNSIGNED NULL,          -- mensalidade
  session_minutes SMALLINT UNSIGNED NOT NULL,      -- informado pela administração (nada é presumido)
  price_cents INT UNSIGNED NULL,
  paid_on DATE NULL,
  method ENUM('pix','cartao','boleto','dinheiro','transferencia','outro') NULL,
  location VARCHAR(200) NULL,
  starts_on DATE NOT NULL,
  valid_until DATE NULL,                            -- NULL = sem validade definida
  status ENUM('active','cancelled') NOT NULL DEFAULT 'active',
  cancel_reason VARCHAR(300) NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_pp_a (athlete_id, status),
  KEY ix_pp_t (trainer_id, status),
  CONSTRAINT fk_pp_a FOREIGN KEY (athlete_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pp_t FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pt_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  package_id BIGINT UNSIGNED NOT NULL,
  athlete_id BIGINT UNSIGNED NOT NULL,
  trainer_id BIGINT UNSIGNED NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  status ENUM('requested','approved','refused','done','missed','cancelled') NOT NULL DEFAULT 'requested',
  consumes TINYINT(1) NOT NULL DEFAULT 0,           -- se descontou uma sessão do saldo
  note VARCHAR(300) NULL,
  decided_by BIGINT UNSIGNED NULL,
  decided_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_ps_trainer (trainer_id, starts_at),
  KEY ix_ps_athlete (athlete_id, starts_at),
  KEY ix_ps_package (package_id, status),
  CONSTRAINT fk_ps_p FOREIGN KEY (package_id) REFERENCES pt_packages(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
