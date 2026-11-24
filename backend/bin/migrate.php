<?php
declare(strict_types=1);
// Uso: php backend/bin/migrate.php
// Aplica migrações novas em ordem. Só roda arquivos ainda não registrados; nunca apaga dados.
require dirname(__DIR__) . '/src/bootstrap.php';

use Correon\Db;

$pdo = Db::pdo();
$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
  filename VARCHAR(120) NOT NULL PRIMARY KEY,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

$done = array_column(Db::all('SELECT filename FROM schema_migrations'), 'filename');
$files = glob(dirname(__DIR__) . '/migrations/*.sql') ?: [];
sort($files);
$applied = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $done, true)) {
        continue;
    }
    // DDL do MariaDB faz commit implícito; cada arquivo deve ser idempotente (IF NOT EXISTS).
    $pdo->exec((string) file_get_contents($file));
    Db::run('INSERT INTO schema_migrations (filename) VALUES (?)', [$name]);
    echo "aplicada: $name\n";
    $applied++;
}
echo $applied ? "OK ($applied nova(s)).\n" : "Nada a aplicar.\n";
