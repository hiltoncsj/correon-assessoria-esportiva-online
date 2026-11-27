<?php
declare(strict_types=1);
namespace Correon;

/** API pública, somente leitura, sem sessão nem cookies. */
final class Api
{
    public static function handle(string $method, string $path): never
    {
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        try {
            if ($method === 'GET' && $path === '/api/v1/public/comunidade') {
                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: public, max-age=60');
                echo json_encode(Content::publicData(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
                exit;
            }
            // tarefa agendada: exige a chave CRON_TOKEN; sem chave configurada, a rota não existe
            if ($method === 'GET' && $path === '/api/v1/cron/lembretes') {
                $token = (string) Env::get('CRON_TOKEN', '');
                $sent = (string) ($_SERVER['HTTP_X_CRON_TOKEN'] ?? (is_string($_GET['token'] ?? null) ? $_GET['token'] : ''));
                if (strlen($token) >= 32 && hash_equals($token, $sent)) {
                    header('Content-Type: application/json; charset=utf-8');
                    header('Cache-Control: no-store');
                    echo json_encode(Reminders::run());
                    exit;
                }
            }
            if ($method === 'GET' && $path === '/api/v1/public/promocao') {
                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: no-store');
                echo json_encode(Promo::publicData(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
                exit;
            }
            if ($method === 'GET' && preg_match('#^/api/v1/public/promocao/midia/(\d+)$#', $path, $pm)) {
                Promo::publicMedia((int) $pm[1]);
            }
            if ($method === 'GET' && $path === '/api/v1/public/indicadores') {
                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: public, max-age=300');
                echo json_encode(['objetivos' => Goals::publicCount()]);
                exit;
            }
            if ($method === 'GET' && preg_match('#^/api/v1/public/imagem/(\d+)$#', $path, $m) && Content::imageIsPublic((int) $m[1])) {
                Media::output((int) $m[1], true);
            }
            http_response_code(404);
        } catch (\Throwable $e) {
            error_log('[api] ' . $e->getMessage());
            http_response_code(500);
        }
        header('Content-Type: application/json; charset=utf-8');
        echo '{"error":true}';
        exit;
    }
}
