# Publicação na LocalWeb

## Antes de começar (confirmar)
- PHP **8.3** selecionado, com extensões pdo_mysql, mbstring, **gd**, **exif**, zlib.
- `mod_rewrite` ativo, SSL ativo, envio de e-mail (`mail()`/SMTP) funcionando.
- Banco MariaDB **vazio** e suas credenciais.
- Textos de Termos e Privacidade publicados antes de abrir cadastros (ver `PENDENCIAS.md`).

## 1. Gerar o pacote (no seu computador, pasta `SiteCorreOn/`)
```
npm run deploy:package
```
Cria `deploy/` com:
```
deploy/public_html/        -> o CONTEÚDO vai para o public_html
deploy/correon/            -> vai FORA do public_html (ao lado dele)
deploy/instalar-banco.sql  -> instalação do banco vazio
deploy/LEIA-ME.md          -> este guia
```

## 2. Banco de dados
phpMyAdmin → banco → **Importar** `instalar-banco.sql`. (Somente em banco vazio.)

## 3. Primeiro administrador (sem terminal no servidor)
No seu computador, na pasta `SiteCorreOn/`:
```
C:\xampp\php\php.exe backend\bin\admin-sql.php "Seu Nome" seu@email.com
```
Digite uma senha de 12+ caracteres, copie as linhas geradas e execute na aba **SQL** do phpMyAdmin. Não salve esse texto em arquivo.

## 4. Enviar pelo FileZilla
1. Conteúdo de `deploy/public_html/` → `public_html/`. Ative "mostrar arquivos ocultos": `.htaccess` e `.user.ini` precisam ir.
2. Pasta `deploy/correon/` → **ao lado** do `public_html`, nunca dentro.
3. Permissão de escrita em `correon/storage/`.

## 5. Configurar
Crie `correon/.env` a partir de `correon/.env.example`: `APP_ENV=production`, `APP_URL=https://seudominio`, `DB_*` reais, `MAIL_DRIVER=mail`, `MAIL_FROM` de um endereço do domínio. **Nunca** envie o `.env` do seu computador.

## 6. Conferir
- Se houver SSH ou cron de comando: `php correon/bin/check.php` deve terminar com "Tudo certo".
- No `.htaccess`, **descomente o redirecionamento para HTTPS** depois de ver o SSL funcionando.
- No navegador: `/` (site), `/entrar`, login do administrador, `/cadastro` com um e-mail seu, **recuperar senha** (o e-mail precisa chegar), criar um treinador, publicar uma notícia e vê-la na seção "Em movimento".
- `https://seudominio/.user.ini` deve dar acesso negado.

## 7. Depois
- Agendar o backup (`BACKUP.md`).
- Olhar `correon/storage/php-error.log` nos primeiros dias.

## Atualizações futuras
Faça backup antes. `npm run deploy:package` e envie `public_html/` e `correon/` **sem apagar** `correon/.env` nem `correon/storage/`. Migração nova: `php correon/bin/migrate.php` (cron/SSH) ou execute o `.sql` novo de `correon/migrations/` no phpMyAdmin e registre: `INSERT INTO schema_migrations (filename) VALUES ('nome.sql');`
