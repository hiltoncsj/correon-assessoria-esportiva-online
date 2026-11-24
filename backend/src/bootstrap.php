<?php
declare(strict_types=1);

/** Carrega autoloader, .env, erros e fuso. BASE = pasta que contém src/, views/ e storage/. */
define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Correon\\')) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 8)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

date_default_timezone_set('America/Sao_Paulo');
mb_internal_encoding('UTF-8');

// .env: BASE/.env (produção, fora do public_html) ou BASE/../.env (desenvolvimento)
foreach ([BASE_PATH . '/.env', dirname(BASE_PATH) . '/.env'] as $envFile) {
    if (is_file($envFile)) {
        Correon\Env::load($envFile);
        break;
    }
}

$isProd = Correon\Env::get('APP_ENV', 'production') === 'production';
ini_set('display_errors', $isProd ? '0' : '1');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/php-error.log');
error_reporting(E_ALL);

/** Escapa HTML para as views. */
function e(?string $s): string
{
    return Correon\View::e($s);
}
