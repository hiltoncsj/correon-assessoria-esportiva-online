<?php
declare(strict_types=1);
namespace Correon;

final class Env
{
    /** @var array<string,string> */
    private static array $vars = [];

    public static function load(string $file): void
    {
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $v = trim($v);
            if ($v !== '' && ($v[0] === '"' || $v[0] === "'")) {
                $q = $v[0];
                $end = strrpos($v, $q);
                $v = $end > 0 ? substr($v, 1, $end - 1) : substr($v, 1);
            } else {
                $v = trim(preg_replace('/\s+#.*$/', '', $v) ?? $v);
            }
            self::$vars[trim($k)] = $v;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        // Variável de ambiente real tem precedência sobre o arquivo .env (útil nos testes).
        $e = getenv($key);
        if ($e !== false) {
            return (string) $e;
        }
        return self::$vars[$key] ?? $default;
    }

    public static function isProd(): bool
    {
        return self::get('APP_ENV', 'production') === 'production';
    }
}
