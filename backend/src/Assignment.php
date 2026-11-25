<?php
declare(strict_types=1);
namespace Correon;

/** Vínculo treinador-atleta por modalidade. Regra única, usada pela tela do admin e pelo script de linha de comando. */
final class Assignment
{
    public const OK = 'ok';
    public const BLOCKED = 'blocked';       // treinador no limite e sem motivo de exceção
    public const INVALID = 'invalid';       // perfil errado, conta bloqueada ou modalidade inexistente

    private static function hasRole(int $userId, string $role): bool
    {
        return Db::one("SELECT 1 ok FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id
                        WHERE u.id = ? AND r.slug = ? AND u.status = 'active'", [$userId, $role]) !== null;
    }

    /** @return array{result:string,exception:bool} */
    public static function assign(int $trainerId, int $athleteId, string $modality, string $reason = '', ?int $byUser = null): array
    {
        $modId = Workout::modalityId($modality);
        if ($modId === null || !self::hasRole($trainerId, 'trainer') || !self::hasRole($athleteId, 'athlete')) {
            return ['result' => self::INVALID, 'exception' => false];
        }
        $reason = trim($reason);
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            // trava a linha do treinador: duas atribuições simultâneas não furam o limite
            Db::one('SELECT id FROM users WHERE id = ? FOR UPDATE', [$trainerId]);
            $exception = !Capacity::canAssign($trainerId, $athleteId);
            if ($exception && $reason === '') {
                $pdo->rollBack();
                Audit::log('capacity_blocked', $byUser, 'user', $athleteId, ['trainer_id' => $trainerId]);
                return ['result' => self::BLOCKED, 'exception' => false];
            }
            if ($exception) {
                Db::run('INSERT INTO capacity_exceptions (trainer_id, athlete_id, reason) VALUES (?,?,?)', [$trainerId, $athleteId, mb_substr($reason, 0, 300)]);
            }
            Db::run("INSERT INTO trainer_athletes (trainer_id, athlete_id, modality_id) VALUES (?,?,?)
                     ON DUPLICATE KEY UPDATE status = 'active', ended_at = NULL, started_at = IF(status = 'ended', NOW(), started_at)",
                [$trainerId, $athleteId, $modId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        if ($exception) {
            Audit::log('capacity_exception', $byUser, 'user', $athleteId, ['trainer_id' => $trainerId]);
        }
        Audit::log('trainer_assigned', $byUser, 'user', $athleteId, ['trainer_id' => $trainerId, 'modality' => $modality]);
        return ['result' => self::OK, 'exception' => $exception];
    }

    /** Encerra o vínculo (histórico preservado). Retorna false se não havia vínculo ativo. */
    public static function end(int $trainerId, int $athleteId, string $modality, ?int $byUser = null): bool
    {
        $st = Db::run("UPDATE trainer_athletes SET status = 'ended', ended_at = NOW() WHERE trainer_id = ? AND athlete_id = ? AND modality_id = ? AND status = 'active'",
            [$trainerId, $athleteId, Workout::modalityId($modality) ?? 0]);
        if ($st->rowCount() < 1) {
            return false;
        }
        Audit::log('trainer_unassigned', $byUser, 'user', $athleteId, ['trainer_id' => $trainerId, 'modality' => $modality]);
        return true;
    }
}
