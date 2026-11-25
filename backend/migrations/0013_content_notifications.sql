-- Sprint 8: conteúdo público (resultados, notícias, eventos), fotos, avisos e revogação de consentimento.
CREATE TABLE IF NOT EXISTS media (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(80) NOT NULL,
  mime VARCHAR(30) NOT NULL,
  width SMALLINT UNSIGNED NOT NULL,
  height SMALLINT UNSIGNED NOT NULL,
  uploaded_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_media_file (filename),
  CONSTRAINT fk_media_u FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  type ENUM('result','news','event') NOT NULL,
  title VARCHAR(160) NOT NULL,                      -- resultado: nome do atleta · notícia: título · evento: nome
  content_date DATE NOT NULL,
  category VARCHAR(60) NULL,                        -- modalidade (resultado/evento) ou categoria (notícia)
  competition VARCHAR(160) NULL,
  distance VARCHAR(60) NULL,
  result_text VARCHAR(60) NULL,
  placement VARCHAR(60) NULL,
  summary VARCHAR(600) NULL,
  place VARCHAR(160) NULL,
  event_time VARCHAR(20) NULL,
  event_status ENUM('proximo','andamento','realizado') NULL,   -- NULL = calculado pela data
  link_url VARCHAR(300) NULL,
  registration_url VARCHAR(300) NULL,
  image_id BIGINT UNSIGNED NULL,
  image_alt VARCHAR(200) NULL,
  athlete_id BIGINT UNSIGNED NULL,                  -- atleta cadastrado citado (exige consentimento dele)
  consent_declared TINYINT(1) NOT NULL DEFAULT 0,   -- admin declara ter autorização (imagem / pessoa sem cadastro)
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  publish_at DATETIME NULL,                         -- agendamento (NULL = imediato)
  created_by BIGINT UNSIGNED NOT NULL,
  updated_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_ct_public (type, status, content_date),
  CONSTRAINT fk_cnt_img FOREIGN KEY (image_id) REFERENCES media(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cnt_ath FOREIGN KEY (athlete_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL,
  body VARCHAR(600) NULL,
  link VARCHAR(200) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME NULL,
  KEY ix_nt_user (user_id, read_at, id),
  CONSTRAINT fk_nt_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE consents ADD COLUMN IF NOT EXISTS revoked_at DATETIME NULL;
