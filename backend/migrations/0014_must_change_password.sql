-- Senha provisória definida pelo admin: a pessoa precisa trocar no primeiro acesso.
ALTER TABLE users ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) NOT NULL DEFAULT 0;
