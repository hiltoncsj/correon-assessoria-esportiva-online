-- Sprint 3 (ajuste 2): modelos SEMANAIS por nível, zonas de frequência cardíaca por atleta e treino padrão por atleta.
-- Somente CREATE / ADD COLUMN / backfill idempotente (sem DROP).

-- Zona de FC em cada linha de treino (o bpm vem das zonas do atleta).
ALTER TABLE workout_items ADD COLUMN IF NOT EXISTS hr_zone TINYINT UNSIGNED NULL;
ALTER TABLE workout_template_items ADD COLUMN IF NOT EXISTS hr_zone TINYINT UNSIGNED NULL;

-- Modelo = semana; cada sessão cai em um dia da semana (1 = segunda ... 7 = domingo).
CREATE TABLE IF NOT EXISTS workout_template_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  template_id BIGINT UNSIGNED NOT NULL,
  weekday TINYINT UNSIGNED NOT NULL,
  position SMALLINT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL,
  discipline ENUM('corrida','ciclismo','natacao','musculacao') NOT NULL,
  notes VARCHAR(1000) NULL,
  KEY ix_wts_template (template_id, position),
  CONSTRAINT fk_wts_t FOREIGN KEY (template_id) REFERENCES workout_templates(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE workout_template_items ADD COLUMN IF NOT EXISTS session_id BIGINT UNSIGNED NULL;
ALTER TABLE workout_templates ADD COLUMN IF NOT EXISTS level_description VARCHAR(120) NULL;

-- Modelos criados antes desta migração viram uma sessão única (segunda-feira).
INSERT INTO workout_template_sessions (template_id, weekday, position, title, discipline, notes)
  SELECT t.id, 1, 1, t.name, t.discipline, t.notes FROM workout_templates t
  WHERE NOT EXISTS (SELECT 1 FROM workout_template_sessions s WHERE s.template_id = t.id);
UPDATE workout_template_items i JOIN workout_template_sessions s ON s.template_id = i.template_id
  SET i.session_id = s.id WHERE i.session_id IS NULL;

-- Treino padrão (nível) do atleta, por vínculo/modalidade.
ALTER TABLE trainer_athletes ADD COLUMN IF NOT EXISTS default_template_id BIGINT UNSIGNED NULL;

-- Zonas de frequência cardíaca individuais.
CREATE TABLE IF NOT EXISTS athlete_hr_zones (
  athlete_id BIGINT UNSIGNED NOT NULL,
  zone TINYINT UNSIGNED NOT NULL,
  bpm_min SMALLINT UNSIGNED NOT NULL,
  bpm_max SMALLINT UNSIGNED NOT NULL,
  updated_by BIGINT UNSIGNED NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (athlete_id, zone),
  CONSTRAINT fk_hz_a FOREIGN KEY (athlete_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_hz_u FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Zonas vigentes quando o atleta registrou o treino (o histórico não muda se as zonas mudarem depois).
ALTER TABLE workout_logs ADD COLUMN IF NOT EXISTS zones_snapshot TEXT NULL;
