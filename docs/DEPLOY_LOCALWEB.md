# Deploy na LocalWeb (Landing + área PHP)

## Estrutura no servidor

```
/home/SEU_USUARIO/
├── correon/                 ← FORA do public_html (não acessível pela web)
│   ├── src/  views/  migrations/  bin/
│   ├── storage/             (permissão de escrita; sessões, logs, e-mails de teste)
│   └── .env                 (credenciais de produção, criado por você no servidor)
└── public_html/             ← conteúdo de dist/ + arquivos abaixo
    ├── (dist/*: index.html, _astro/, favicon...)
    ├── app.php              (de backend/public/app.php)
    ├── .user.ini            (de backend/public/.user.ini)
    ├── .htaccess            (de backend/public/.htaccess)
    └── assets/              (app.css, tokens.css, logo-correon.png)
```

## Passo a passo

1. Local: `npm run build` e `npm run sync:app-assets`.
2. FileZilla: envie `dist/*` para `public_html/`; envie `backend/public/app.php`, `backend/public/.htaccess` e `backend/public/assets/*` (para `public_html/assets/`, sem sobrescrever `_astro/`).
3. Envie `backend/src`, `backend/views`, `backend/migrations`, `backend/bin` para `correon/` e crie `correon/storage/` (escrita).
4. Crie `correon/.env` no servidor a partir de `.env.example` (`APP_ENV=production`, `DB_*` reais, `MAIL_DRIVER=mail`). **Nunca** envie o `.env` local.
5. Aplicar migrações (sem SSH): temporariamente, via painel/cron rodando `php ~/correon/bin/migrate.php`, ou importando os `.sql` de `migrations/` no phpMyAdmin, em ordem.
6. Criar o primeiro administrador: `CORREON_NEW_PASSWORD='...' php ~/correon/bin/create-user.php admin "Nome" email` (via cron único ou SSH, se houver).
7. Ative o redirecionamento HTTPS no `.htaccess` (linhas comentadas) depois de confirmar o SSL.
8. Teste: `/entrar`, `/cadastro`, `/recuperar-senha` (e-mail chega?), acesso negado entre perfis.

## Pendente de confirmar com a LocalWeb
SSH/Composer, `mod_rewrite` ativo, SMTP/`mail()` entregando e-mail, versão do PHP selecionada (8.3).
