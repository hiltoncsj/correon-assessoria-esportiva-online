<?php
declare(strict_types=1);
namespace Correon;

final class RateLimiter
{
    /** True se já passou do limite (não registra). $windowSeconds é inteiro controlado pelo código. */
    public static function tooMany(string $kind, string $value, int $max, int $windowSeconds, bool $byIp = false): bool
    {
        $col = $byIp ? 'ip' : 'subject';
        $row = Db::one(
            "SELECT COUNT(*) c FROM rate_events WHERE kind = ? AND $col = ? AND created_at > (NOW() - INTERVAL " . (int) $windowSeconds . ' SECOND)',
            [$kind, $value]
        );
        return (int) ($row['c'] ?? 0) >= $max;
    }

    public static function hit(string $kind, string $subject): void
    {
        Db::run('INSERT INTO rate_events (kind, subject, ip) VALUES (?,?,?)', [$kind, substr($subject, 0, 190), Http::ip()]);
        if (random_int(1, 50) === 1) {
            Db::run('DELETE FROM rate_events WHERE created_at < (NOW() - INTERVAL 2 DAY)');
        }
    }

    public static function clear(string $kind, string $subject): void
    {
        Db::run('DELETE FROM rate_events WHERE kind = ? AND subject = ?', [$kind, $subject]);
    }
}
