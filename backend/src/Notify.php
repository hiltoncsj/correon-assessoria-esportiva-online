<?php
declare(strict_types=1);
namespace Correon;

/** Avisos dentro da plataforma (sem e-mail/WhatsApp). Uma falha aqui nunca desfaz a ação principal. */
final class Notify
{
    public static function send(int $userId, string $title, ?string $body = null, ?string $link = null): void
    {
        try {
            Db::run('INSERT INTO notifications (user_id, title, body, link) VALUES (?,?,?,?)',
                [$userId, mb_substr($title, 0, 120), $body === null ? null : mb_substr($body, 0, 600), $link]);
        } catch (\Throwable $e) {
            error_log('notify failure: ' . $e->getMessage());
        }
    }

    public static function unread(int $userId): int
    {
        return (int) Db::one('SELECT COUNT(*) c FROM notifications WHERE user_id = ? AND read_at IS NULL', [$userId])['c'];
    }
}
