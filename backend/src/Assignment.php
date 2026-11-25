<?php
declare(strict_types=1);
namespace Correon;

/** Vínculo treinador-atleta por modalidade. Regra única, usada pela tela do admin e pelo script de linha de comando. */
final class Assignment
{
    public const OK = 'ok';
    public const BLOCKED = 'blocked';       // treinador no limite e sem motivo de exceção
    public const INVALID = 'invalid';       // perfil errado, conta bloqueada ou modalidade inexistente
    public const NOT_ATTENDED = 'not_attended'; // o treinador não atende essa modalidade

    /** Slugs das modalidades que o treinador atende. @return list<string> */
    public static function modalities(int $trainerId): array
    {
        return array_column(Db::all('SELECT m.slug FROM trainer_modalities tm JOIN modalities m ON m.id = tm.modality_id WHERE tm.trainer_id = ? ORDER BY m.id', [$trainerId]), 'slug');
    }

    /**
     * Define as modalidades do treinador. Não permite retirar uma modalidade com atletas ativos.
     * @param list<string> $slugs  @return string|null erro
     */
    public static function setModalities(int $trainerId, array $slugs, ?int $byUser = null): ?string
    {
        $all = array_column(Db::all('SELECT id, slug, name FROM modalities ORDER BY id'), null, 'slug');
        $wanted = array_values(array_intersect(array_keys($all), $slugs));
        foreach (array_diff(self::modalities($trainerId), $wanted) as $removed) {
            $n = (int) Db::one("SELECT COUNT(*) c FROM trainer_athletes WHERE trainer_id = ? AND modality_id = ? AND status = 'active'", [$trainerId, $all[$removed]['id']])['c'];
            if ($n > 0) {
                return "Não é possível retirar {$all[$removed]['name']}: há $n atleta(s) ativo(s) nessa modalidade. Encerre os vínculos primeiro.";
            }
        }
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            Db::run('DELETE FROM trainer_modalities WHERE trainer_id = ?', [$trainerId]);
            foreach ($wanted as $slug) {
                Db::run('INSERT INTO trainer_modalities (trainer_id, modality_id) VALUES (?,?)', [$trainerId, $all[$slug]['id']]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        Audit::log('trainer_modalities_set', $byUser, 'user', $trainerId, ['modalities' => $wanted]);
        return null;
    }

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
        if (!in_array($modality, self::modalities($trainerId), true)) {
            return ['result' => self::NOT_ATTENDED, 'exception' => false];
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
