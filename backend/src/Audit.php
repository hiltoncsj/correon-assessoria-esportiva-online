<?php
declare(strict_types=1);
namespace Correon;

final class Audit
{
    /** @param array<string,mixed> $details Nunca incluir senhas, tokens ou dados sensíveis. */
    public static function log(string $action, ?int $userId = null, ?string $entityType = null, ?int $entityId = null, array $details = []): void
    {
        try {
            Db::run(
                'INSERT INTO audit_log (user_id, action, entity_type, entity_id, ip, user_agent, details) VALUES (?,?,?,?,?,?,?)',
                [
                    $userId, $action, $entityType, $entityId, Http::ip(),
                    substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                    $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                ]
            );
        } catch (\Throwable $e) {
            error_log('audit failure: ' . $e->getMessage());
        }
    }
}
