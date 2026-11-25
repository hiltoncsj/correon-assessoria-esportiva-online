// Monta a pasta deploy/ pronta para o FileZilla. Uso: npm run deploy:package
// Não inclui .env, testes, dados locais (storage) nem o roteador de desenvolvimento.
import { cpSync, rmSync, mkdirSync, writeFileSync, readFileSync, readdirSync, existsSync } from 'node:fs';
import { execSync } from 'node:child_process';

execSync('npm run build', { stdio: 'inherit' });
execSync('node scripts/sync-app-assets.mjs', { stdio: 'inherit' });

rmSync('deploy', { recursive: true, force: true });
const web = 'deploy/public_html';
const app = 'deploy/correon';
mkdirSync(web, { recursive: true });
mkdirSync(`${app}/storage`, { recursive: true });

// público: Landing + front controller + arquivos estáticos da área logada
cpSync('dist', web, { recursive: true });
for (const f of ['app.php', '.htaccess', '.user.ini']) cpSync(`backend/public/${f}`, `${web}/${f}`);
cpSync('backend/public/assets', `${web}/assets`, { recursive: true });
if (existsSync('backend/public/favicon.svg')) cpSync('backend/public/favicon.svg', `${web}/favicon.svg`);

// privado: fora do public_html
for (const d of ['src', 'views', 'migrations', 'bin']) cpSync(`backend/${d}`, `${app}/${d}`, { recursive: true });
cpSync('.env.example', `${app}/.env.example`);
writeFileSync(`${app}/storage/.gitkeep`, '');
// trava extra, caso a pasta seja enviada por engano para dentro do public_html
writeFileSync(`${app}/.htaccess`, 'Require all denied\n');

// SQL único para instalar um banco VAZIO pelo phpMyAdmin (registra as migrações como aplicadas)
const files = readdirSync('backend/migrations').filter((f) => f.endsWith('.sql')).sort();
let sql = '-- Instalação do banco CorreON (somente em banco VAZIO). Gerado por scripts/build-deploy.mjs\nSET NAMES utf8mb4;\n';
sql += 'CREATE TABLE IF NOT EXISTS schema_migrations (filename VARCHAR(120) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n';
for (const f of files) {
	sql += `\n-- ===== ${f} =====\n${readFileSync(`backend/migrations/${f}`, 'utf8')}\nINSERT IGNORE INTO schema_migrations (filename) VALUES ('${f}');\n`;
}
writeFileSync('deploy/instalar-banco.sql', sql);
cpSync('docs/DEPLOY_LOCALWEB.md', 'deploy/LEIA-ME.md');
console.log(`\nPacote pronto em deploy/ (${files.length} migrações no instalar-banco.sql). Leia deploy/LEIA-ME.md.`);
