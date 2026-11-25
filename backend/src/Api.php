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
