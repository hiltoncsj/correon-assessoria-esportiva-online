-- Quando o lembrete de resultado foi enviado (um por atleta e prova).
ALTER TABLE goal_participants ADD COLUMN IF NOT EXISTS reminded_at DATETIME NULL;
