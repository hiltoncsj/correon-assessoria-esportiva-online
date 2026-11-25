<?php
declare(strict_types=1);
// Uso (cron ou terminal): php backup.php
// Gera uma cópia do banco em storage/backups/ (compactada) e mantém as 14 mais recentes.
// As fotos ficam em storage/uploads/ e precisam ser copiadas à parte (FileZilla). Contém dados pessoais.
require dirname(__DIR__) . '/src/bootstrap.php';

use Correon\Db;

if (PHP_SAPI !== 'cli' && isset($_SERVER['REMOTE_ADDR'])) {
    http_response_code(404);
    exit;
}

$dir = BASE_PATH . '/storage/backups';
if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
    fwrite(STDERR, "Não foi possível criar $dir\n");
    exit(1);
}
$pdo = Db::pdo();
$file = $dir . '/correon-' . date('Ymd-His') . '.sql.gz';
$gz = gzopen($file, 'wb9');
$w = static function (string $s) use ($gz): void { gzwrite($gz, $s); };
$w("-- Backup CorreON " . date('c') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");

$pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$pdo->beginTransaction(); // foto consistente do banco (InnoDB)
$rows = 0;
foreach ($pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_NUM) as [$table]) {
    $q = '`' . str_replace('`', '``', $table) . '`';
    $create = $pdo->query("SHOW CREATE TABLE $q")->fetch(PDO::FETCH_NUM)[1];
    $w("\nDROP TABLE IF EXISTS $q;\n$create;\n");
    $st = $pdo->query("SELECT * FROM $q");
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $values = array_map(static fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($r));
        $w("INSERT INTO $q VALUES (" . implode(',', $values) . ");\n");
        $rows++;
    }
}
$pdo->commit();
$w("\nSET FOREIGN_KEY_CHECKS=1;\n");
gzclose($gz);
@chmod($file, 0640);

$old = glob($dir . '/correon-*.sql.gz') ?: [];
rsort($old);
foreach (array_slice($old, 14) as $f) {
    @unlink($f);
}
echo "Backup: " . basename($file) . " ($rows linhas, " . round(filesize($file) / 1024) . " KB)\n";
