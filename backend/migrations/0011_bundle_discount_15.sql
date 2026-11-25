-- Combo = soma dos individuais com 15% de desconto, em todos os períodos (definido em 25/11/2026).
-- Atualiza apenas preços de combo que nenhum administrador alterou. Contratos existentes não mudam.
UPDATE plan_prices SET total_cents = 30600  WHERE period = 'mensal'     AND updated_by IS NULL AND product IN ('corrida+musculacao','triathlon+musculacao');
UPDATE plan_prices SET total_cents = 81600  WHERE period = 'trimestral' AND updated_by IS NULL AND product IN ('corrida+musculacao','triathlon+musculacao');
UPDATE plan_prices SET total_cents = 142800 WHERE period = 'semestral'  AND updated_by IS NULL AND product IN ('corrida+musculacao','triathlon+musculacao');
UPDATE plan_prices SET total_cents = 244800 WHERE period = 'anual'      AND updated_by IS NULL AND product IN ('corrida+musculacao','triathlon+musculacao');
