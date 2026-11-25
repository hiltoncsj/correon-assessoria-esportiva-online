# Backup e restauração

## O que guardar
| Item | Onde | Como |
|---|---|---|
| Banco de dados | MariaDB | `correon/bin/backup.php` (ou "Exportar" no phpMyAdmin) |
| Fotos | `correon/storage/uploads/` | Baixar pelo FileZilla |
| Configuração | `correon/.env` | Guardar cópia em local seguro (tem a senha do banco) |
| Código | GitHub | Já versionado |

Os backups contêm **dados pessoais**: guarde em local protegido e apague cópias antigas.

## Automático (cron da LocalWeb)
Agende uma vez por dia: `php /home/SEU_USUARIO/correon/bin/backup.php`
Gera `correon/storage/backups/correon-AAAAMMDD-HHMMSS.sql.gz` e mantém os 14 mais recentes.
Se o cron do plano só chamar endereços da web (e não comandos), use o backup do painel da LocalWeb/phpMyAdmin: o script se recusa a rodar pela web.

**Uma vez por semana, baixe o arquivo mais recente e a pasta `uploads/` para o seu computador.** Backup só no mesmo servidor não protege contra perda do servidor.

## Restaurar
1. Crie um banco **vazio** (ou confirme que pode substituir o atual; o arquivo apaga e recria as tabelas).
2. phpMyAdmin → Importar → selecione o `.sql.gz`.
3. Envie `uploads/` de volta para `correon/storage/`.
4. Ajuste `DB_NAME` no `.env` se o banco for outro e rode `php correon/bin/check.php`.

## Testado
Em 2026-11-25: backup de uma instalação com dados, restauração em banco novo, conteúdo idêntico (contagens e assinaturas) e login funcionando no banco restaurado. **Repetir o teste na hospedagem** após publicar e a cada poucos meses.
