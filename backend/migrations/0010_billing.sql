-- Sprint 6: preços, contratos, cobranças (baixa manual) e histórico financeiro. Somente CREATE / INSERT IGNORE.
-- Valores sempre em CENTAVOS (inteiros). Nenhum dado de cartão é armazenado.
CREATE TABLE IF NOT EXISTS plan_prices (
  product VARCHAR(40) NOT NULL,                    -- corrida | triathlon | musculacao | corrida+musculacao | triathlon+musculacao
  period ENUM('mensal','trimestral','semestral','anual') NOT NULL,
  total_cents INT UNSIGNED NULL,                   -- NULL = não vendido (sem preço definido)
  max_installments TINYINT UNSIGNED NOT NULL DEFAULT 1,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (product, period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preços informados pelo cliente em 25/11/2026 (combo só tem preço no mensal).
INSERT IGNORE INTO plan_prices (product, period, total_cents, max_installments) VALUES
  ('corrida','mensal',18000,1),('corrida','trimestral',48000,3),('corrida','semestral',84000,6),('corrida','anual',144000,12),
  ('triathlon','mensal',18000,1),('triathlon','trimestral',48000,3),('triathlon','semestral',84000,6),('triathlon','anual',144000,12),
  ('musculacao','mensal',18000,1),('musculacao','trimestral',48000,3),('musculacao','semestral',84000,6),('musculacao','anual',144000,12),
  ('corrida+musculacao','mensal',20000,1),('corrida+musculacao','trimestral',NULL,3),('corrida+musculacao','semestral',NULL,6),('corrida+musculacao','anual',NULL,12),
  ('triathlon+musculacao','mensal',20000,1),('triathlon+musculacao','trimestral',NULL,3),('triathlon+musculacao','semestral',NULL,6),('triathlon+musculacao','anual',NULL,12);

-- Contrato: guarda o preço APLICADO (mudar a tabela não altera contratos existentes).
CREATE TABLE IF NOT EXISTS contracts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  athlete_id BIGINT UNSIGNED NOT NULL,
  product VARCHAR(40) NOT NULL,
  period ENUM('mensal','trimestral','semestral','anual') NOT NULL,
  price_cents INT UNSIGNED NOT NULL,
  installments TINYINT UNSIGNED NOT NULL DEFAULT 1,
  price_condition ENUM('standard','legacy_120','custom') NOT NULL DEFAULT 'standard',
  condition_reason VARCHAR(300) NULL,
  status ENUM('active','cancelled') NOT NULL DEFAULT 'active',
  starts_on DATE NOT NULL,
  ends_on DATE NOT NULL,
  renewed_from BIGINT UNSIGNED NULL,
  cancelled_at DATETIME NULL,
  cancel_reason VARCHAR(300) NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_ct_athlete (athlete_id, status, ends_on),
  UNIQUE KEY uq_ct_renew (renewed_from),
  CONSTRAINT fk_ct_a FOREIGN KEY (athlete_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ct_cb FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cobranças/parcelas: situação do PAGAMENTO separada da situação do contrato.
CREATE TABLE IF NOT EXISTS charges (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  contract_id BIGINT UNSIGNED NOT NULL,
  seq TINYINT UNSIGNED NOT NULL,
  due_on DATE NOT NULL,
  amount_cents INT UNSIGNED NOT NULL,
  status ENUM('open','paid','cancelled') NOT NULL DEFAULT 'open',
  paid_on DATE NULL,
  method ENUM('pix','cartao','boleto','dinheiro','transferencia','outro') NULL,
  external_id VARCHAR(120) NULL,                    -- identificador do gateway (futuro; único = idempotência)
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ch_seq (contract_id, seq),
  UNIQUE KEY uq_ch_ext (external_id),
  KEY ix_ch_due (status, due_on),
  CONSTRAINT fk_ch_c FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Histórico financeiro e contratual (nunca é apagado nem editado).
CREATE TABLE IF NOT EXISTS contract_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  contract_id BIGINT UNSIGNED NOT NULL,
  charge_id BIGINT UNSIGNED NULL,
  event VARCHAR(30) NOT NULL,
  details VARCHAR(400) NULL,
  by_user BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_ce_contract (contract_id, id),
  CONSTRAINT fk_cev_c FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
