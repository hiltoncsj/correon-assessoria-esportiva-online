<?php
declare(strict_types=1);
namespace Correon;

final class View
{
    public static function e(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
        $vars['user'] = Auth::user();
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
