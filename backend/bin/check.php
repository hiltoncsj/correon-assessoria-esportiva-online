<?php
declare(strict_types=1);
// Uso: php check.php — confere se o servidor tem o que a aplicação precisa. Não altera nada.
require dirname(__DIR__) . '/src/bootstrap.php';

use Correon\{Db, Env};

if (PHP_SAPI !== 'cli' && isset($_SERVER['REMOTE_ADDR'])) {
    http_response_code(404);
    exit;
}
$bad = 0;
$check = static function (string $label, bool $ok, string $hint = '') use (&$bad): void {
    $bad += $ok ? 0 : 1;
    echo ($ok ? '[ok]     ' : '[FALHA]  ') . $label . ($ok || $hint === '' ? '' : " -> $hint") . "\n";
};
$check('PHP 8.1 ou superior (atual: ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.1.0', '>='), 'selecione PHP 8.3 no painel');
foreach (['pdo_mysql', 'mbstring', 'gd', 'exif', 'zlib'] as $ext) {
    $check("extensão $ext", extension_loaded($ext), 'ative a extensão no painel');
}
$check('GD grava WebP ou JPEG', function_exists('imagewebp') || function_exists('imagejpeg'));
foreach (['storage', 'storage/sessions', 'storage/uploads', 'storage/backups', 'storage/mail'] as $d) {
    $p = BASE_PATH . '/' . $d;
    if (!is_dir($p)) {
        @mkdir($p, 0750, true);
    }
    $check("pasta gravável: $d", is_dir($p) && is_writable($p), 'dê permissão de escrita');
}
$check('APP_ENV=production', Env::get('APP_ENV') === 'production', 'no .env do servidor');
$check('APP_URL com https://', str_starts_with((string) Env::get('APP_URL'), 'https://'), 'necessário para os links de senha');
$check('MAIL_DRIVER=mail', Env::get('MAIL_DRIVER') === 'mail', '"log" não envia e-mail');
$check('MAIL_FROM preenchido', filter_var(Env::get('MAIL_FROM'), FILTER_VALIDATE_EMAIL) !== false);
try {
    $v = Db::one('SELECT VERSION() v')['v'];
    $check("banco conectado ($v)", true);
    $n = (int) Db::one('SELECT COUNT(*) c FROM schema_migrations')['c'];
    $total = count(glob(BASE_PATH . '/migrations/*.sql') ?: []);
    $check("migrações aplicadas ($n de $total)", $n >= $total, 'importe o SQL ou rode migrate.php');
    $admins = (int) Db::one("SELECT COUNT(*) c FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.slug = 'admin'")['c'];
    $check("administrador cadastrado ($admins)", $admins > 0, 'veja DEPLOY_LOCALWEB.md');
} catch (Throwable $e) {
    $check('banco conectado', false, 'confira DB_* no .env');
}
echo $bad ? "\n$bad item(ns) para corrigir.\n" : "\nTudo certo.\n";
exit($bad ? 1 : 0);
