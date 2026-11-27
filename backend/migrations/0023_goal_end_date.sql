-- target_date passa a ser a data de início; end_date é a data de fim (opcional).
ALTER TABLE goals ADD COLUMN IF NOT EXISTS end_date DATE NULL AFTER target_date;
