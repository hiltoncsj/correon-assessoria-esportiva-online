<?php
declare(strict_types=1);
namespace Correon;

/**
 * Capacidade do treinador. Conta ATLETAS distintos com vínculo ativo (uma segunda modalidade do mesmo
 * atleta não consome vaga). Alerta a partir de 80%; em 100% novas atribuições são bloqueadas, salvo
 * exceção registrada. A capacidade presencial é separada e só passa a ser contada com o Personal Trainer.
 */
final class Capacity
{
    public const DEFAULT_ONLINE = 30;
    public const ALERT_PCT = 80;

    public static function limit(int $trainerId): int
    {
        $r = Db::one('SELECT online_limit FROM trainer_settings WHERE trainer_id = ?', [$trainerId]);
        return $r ? (int) $r['online_limit'] : self::DEFAULT_ONLINE;
    }

    public static function active(int $trainerId): int
    {
        return (int) (Db::one("SELECT COUNT(DISTINCT athlete_id) c FROM trainer_athletes WHERE trainer_id = ? AND status = 'active'", [$trainerId])['c'] ?? 0);
    }

    /** @return array{active:int,limit:int,pct:int,state:string} state: ok | alert | full */
    public static function status(int $trainerId): array
    {
        $active = self::active($trainerId);
        $limit = self::limit($trainerId);
        $pct = $limit > 0 ? (int) floor($active * 100 / $limit) : 100;
        return ['active' => $active, 'limit' => $limit, 'pct' => $pct,
            'state' => $active >= $limit ? 'full' : ($pct >= self::ALERT_PCT ? 'alert' : 'ok')];
    }

    /** Há vaga para vincular este atleta? (quem já é atleta do treinador não ocupa vaga nova) */
    public static function canAssign(int $trainerId, int $athleteId): bool
    {
        $linked = Db::one("SELECT 1 ok FROM trainer_athletes WHERE trainer_id = ? AND athlete_id = ? AND status = 'active' LIMIT 1", [$trainerId, $athleteId]);
        return $linked !== null || self::active($trainerId) < self::limit($trainerId);
    }

    public static function setLimits(int $trainerId, int $online, ?int $presencial): void
    {
        Db::run('INSERT INTO trainer_settings (trainer_id, online_limit, presencial_limit) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE online_limit = VALUES(online_limit), presencial_limit = VALUES(presencial_limit)',
            [$trainerId, $online, $presencial]);
    }
}
