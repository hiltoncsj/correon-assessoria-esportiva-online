<?php
declare(strict_types=1);
namespace Correon;

/** Requisição/resposta, sessão e CSRF. */
final class Http
{
    public static function ip(): string
    {
        // Sem proxy confiável: só REMOTE_ADDR (X-Forwarded-For é forjável).
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? '') === '443';
    }

    public static function secureHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        // se o site estiver em outro endereço (computador local), o "Sair" precisa poder redirecionar para ele
        $site = preg_match('#^https?://[^/\s;,]+#i', View::site(), $m) ? ' ' . $m[0] : '';
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'$site; frame-ancestors 'none'; base-uri 'none'");
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('Cache-Control: no-store');
        if (Env::isProd() || self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }

    public static function startSession(): void
    {
        $dir = BASE_PATH . '/storage/sessions';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
        }
        session_name('correon_sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => Env::isProd() || self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();

        $now = time();
        if (isset($_SESSION['last'], $_SESSION['born'])
            && ($now - $_SESSION['last'] > 7200 || $now - $_SESSION['born'] > 43200)) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['born'] ??= $now;
        $_SESSION['last'] = $now;
    }

    public static function csrfToken(): string
    {
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function checkCsrf(): bool
    {
        $sent = (string) ($_POST['_csrf'] ?? '');
        return $sent !== '' && hash_equals((string) ($_SESSION['csrf'] ?? ''), $sent);
    }

    public static function redirect(string $path): never
    {
        // Só caminhos internos (evita open redirect).
        if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//') || str_contains($path, '\\') || preg_match('/[\r\n]/', $path)) {
            $path = '/';
        }
        header('Location: ' . $path, true, 303);
        exit;
    }

    public static function flash(string $type, string $msg): void
    {
        $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
    }

    /** @return array{type:string,msg:string}|null */
    public static function takeFlash(): ?array
    {
        $f = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $f;
    }

    /**
     * O PHP descarta em silêncio campos além de max_input_vars. Formulários grandes terminam com o campo
     * oculto `_end`; se ele não chegou, o envio veio cortado e nada deve ser gravado.
     */
    public static function formComplete(): bool
    {
        return ($_POST['_end'] ?? '') === '1';
    }

    public static function input(string $key): string
    {
        $v = $_POST[$key] ?? '';
        return is_string($v) ? trim($v) : '';
    }
}
