-- Sprint 3 (ajuste 4): sessões do modelo deixam de ter dia fixo; o dia é escolhido por atleta ao enviar.
-- Apenas afrouxa a coluna (passa a aceitar vazio) e adiciona os dias de treino do atleta. Nada é apagado.
ALTER TABLE workout_template_sessions MODIFY weekday TINYINT UNSIGNED NULL;

-- Dias em que o atleta costuma treinar ("1,3,6": 1 = segunda ... 7 = domingo); usado para pré-montar a semana.
ALTER TABLE trainer_athletes ADD COLUMN IF NOT EXISTS train_days VARCHAR(20) NULL;
