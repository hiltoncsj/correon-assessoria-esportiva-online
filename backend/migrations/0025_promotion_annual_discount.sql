-- Desconto (%) que a promoção dá nos preços anuais (vazio = sem desconto).
ALTER TABLE promotions ADD COLUMN IF NOT EXISTS annual_discount_pct DECIMAL(4,2) NULL AFTER link_url;
