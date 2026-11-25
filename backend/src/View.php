<?php
declare(strict_types=1);
namespace Correon;

final class View
{
    public static function e(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Página principal (Landing). Em produção é "/"; em desenvolvimento use SITE_URL no .env. */
    public static function site(): string
    {
        return (string) Env::get('SITE_URL', '/');
    }

    /** URL de um arquivo estático com a versão (data de modificação), para o navegador não usar cópia antiga. */
    public static function asset(string $path): string
    {
        $file = dirname((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) . $path;
        return $path . (is_file($file) ? '?v=' . filemtime($file) : '');
    }

    /** 2026-11-25 -> "qua, 25/11/2026" */
    public static function dateBr(?string $ymd, bool $weekday = true): string
    {
        if (!$ymd) {
            return '—';
        }
        $t = strtotime($ymd);
        $dias = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
        return ($weekday ? $dias[(int) date('w', $t)] . ', ' : '') . date('d/m/Y', $t);
    }

    /** @param array<string,mixed> $vars */
    public static function render(string $view, array $vars = [], int $status = 200): never
    {
        http_response_code($status);
        $vars['flash'] = Http::takeFlash();
        try {
            $vars['user'] = Auth::user();
        } catch (\Throwable $e) {
            error_log('[view] ' . $e->getMessage());
            $vars['user'] = null; // banco indisponível: a página de erro ainda é exibida
        }
        $vars['csrf'] = Http::csrfToken();
        extract($vars, EXTR_SKIP);
        ob_start();
        require BASE_PATH . '/views/' . $view . '.php';
        $content = ob_get_clean();
        if (!empty($shell)) {
            $inner = $content;
            ob_start();
            require BASE_PATH . '/views/shell/' . $shell . '.php';
            $content = ob_get_clean();
        }
        $title = $title ?? 'CorreON';
        require BASE_PATH . '/views/layout/main.php';
        exit;
    }
}
