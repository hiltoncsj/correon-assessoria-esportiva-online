-- Exclusão de conta de treinador preservando o histórico (marca quando foi excluída).
ALTER TABLE users ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
