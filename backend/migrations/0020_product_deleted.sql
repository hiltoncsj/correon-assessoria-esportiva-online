-- Produto excluído que já teve pedido: sai da loja e das listas, mas o histórico permanece.
ALTER TABLE products ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
