<?php
declare(strict_types=1);
namespace Correon;

/**
 * Regras do Personal Trainer (presencial). Nada comercial é presumido: preço, duração, quantidade,
 * validade e local são informados pela administração; se falta/cancelamento desconta sessão é decidido
 * pelo treinador ao registrar.
 */
final class Personal
{
    public const FORMATS = ['pacote' => 'Pacote de sessões', 'mensal' => 'Mensalidade (frequência semanal)', 'avulsa' => 'Sessão avulsa'];
    public const STATUS = ['requested' => 'Aguardando o treinador', 'approved' => 'Confirmada', 'refused' => 'Recusada', 'done' => 'Realizada', 'missed' => 'Falta', 'cancelled' => 'Cancelada'];

    /** Atletas distintos com pacote presencial ativo e dentro da validade. */
    public static function activeAthletes(int $trainerId): int
    {
        return (int) Db::one("SELECT COUNT(DISTINCT athlete_id) c FROM pt_packages WHERE trainer_id = ? AND status = 'active' AND (valid_until IS NULL OR valid_until >= CURDATE())", [$trainerId])['c'];
    }

    public static function presentialLimit(int $trainerId): ?int
    {
        $r = Db::one('SELECT presencial_limit FROM trainer_settings WHERE trainer_id = ?', [$trainerId]);
        return $r && $r['presencial_limit'] !== null ? (int) $r['presencial_limit'] : null;
    }

    /** Saldo do pacote. @return array{used:int,booked:int,remaining:int|null} remaining = null na mensalidade */
    public static function balance(array $p): array
    {
        $r = Db::one(
            "SELECT
               SUM(status = 'done' OR (status IN ('missed','cancelled') AND consumes = 1)) AS used,
               SUM(status IN ('requested','approved')) AS booked
             FROM pt_sessions WHERE package_id = ?",
            [$p['id']]
        );
        $used = (int) ($r['used'] ?? 0);
        $booked = (int) ($r['booked'] ?? 0);
        return ['used' => $used, 'booked' => $booked,
            'remaining' => $p['format'] === 'mensal' ? null : max(0, (int) $p['sessions_total'] - $used)];
    }

    /**
     * @param array<string,string> $in
     * @return array{id:int|null,error:string|null}
     */
    public static function createPackage(int $athleteId, array $in, int $adminId): array
    {
        $fail = static fn (string $m) => ['id' => null, 'error' => $m];
        $int = static fn (string $v, int $min, int $max): ?int => ctype_digit($v) && (int) $v >= $min && (int) $v <= $max ? (int) $v : null;
        $format = $in['format'];
        if (!isset(self::FORMATS[$format])) {
            return $fail('Escolha o formato.');
        }
        $trainerId = (int) $in['trainer_id'];
        $trainer = Db::one("SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id AND r.slug = 'trainer' WHERE u.id = ? AND u.status = 'active'", [$trainerId]);
        if (!$trainer) {
            return $fail('Escolha um treinador ativo.');
        }
        $minutes = $int($in['session_minutes'], 15, 240);
        if ($minutes === null) {
            return $fail('Informe a duração da sessão em minutos (15 a 240).');
        }
        $total = $weekly = null;
        if ($format === 'pacote' && ($total = $int($in['sessions_total'], 2, 200)) === null) {
            return $fail('Informe quantas sessões o pacote tem (2 a 200).');
        }
        if ($format === 'avulsa') {
            $total = 1;
        }
        if ($format === 'mensal' && ($weekly = $int($in['weekly_frequency'], 1, 7)) === null) {
            return $fail('Informe a frequência semanal (1 a 7).');
        }
        $price = null;
        if (trim($in['price']) !== '' && ($price = Billing::parseMoney($in['price'])) === null) {
            return $fail('Valor inválido (ex.: 150,00) ou deixe em branco.');
        }
        if (!Billing::validDate($in['starts_on'])) {
            return $fail('Data de início inválida.');
        }
        $valid = trim($in['valid_until']);
        if ($valid !== '' && (!Billing::validDate($valid) || $valid < $in['starts_on'])) {
            return $fail('Validade inválida (deve ser depois do início) ou deixe em branco.');
        }
        if ($format === 'mensal' && $valid === '') {
            return $fail('Na mensalidade, informe até quando ela vale.');
        }
        $limit = self::presentialLimit($trainerId);
        $reason = trim($in['reason']);
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            Db::one('SELECT id FROM users WHERE id = ? FOR UPDATE', [$trainerId]);
            $already = Db::one("SELECT id FROM pt_packages WHERE trainer_id = ? AND athlete_id = ? AND status = 'active' AND (valid_until IS NULL OR valid_until >= CURDATE())", [$trainerId, $athleteId]);
            $full = $limit !== null && !$already && self::activeAthletes($trainerId) >= $limit;
            if ($full && $reason === '') {
                $pdo->rollBack();
                return $fail("O treinador está no limite presencial ($limit). Para abrir exceção, preencha o motivo.");
            }
            if ($full) {
                Db::run('INSERT INTO capacity_exceptions (trainer_id, athlete_id, reason) VALUES (?,?,?)', [$trainerId, $athleteId, mb_substr('Presencial: ' . $reason, 0, 300)]);
            }
            Db::run('INSERT INTO pt_packages (athlete_id, trainer_id, format, sessions_total, weekly_frequency, session_minutes, price_cents, location, starts_on, valid_until, created_by)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [$athleteId, $trainerId, $format, $total, $weekly, $minutes, $price, trim($in['location']) === '' ? null : mb_substr(trim($in['location']), 0, 200),
                    $in['starts_on'], $valid === '' ? null : $valid, $adminId]);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        Audit::log('pt_package_created', $adminId, 'pt_package', $id, ['athlete_id' => $athleteId, 'trainer_id' => $trainerId, 'format' => $format]);
        return ['id' => $id, 'error' => null];
    }

    /** Pedido de horário feito pelo atleta. @return string|null erro */
    public static function request(int $athleteId, int $packageId, string $date, string $time): ?string
    {
        if (!Billing::validDate($date) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            return 'Informe data e horário válidos.';
        }
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $p = Db::one("SELECT * FROM pt_packages WHERE id = ? AND athlete_id = ? AND status = 'active' FOR UPDATE", [$packageId, $athleteId]);
            $error = null;
            if (!$p) {
                $error = 'Pacote não disponível.';
            } else {
                $start = new \DateTimeImmutable("$date $time:00");
                $end = $start->modify('+' . (int) $p['session_minutes'] . ' minutes');
                $weekday = (int) $start->format('N');
                if ($start <= new \DateTimeImmutable('now')) {
                    $error = 'Escolha um horário no futuro.';
                } elseif ($date < $p['starts_on'] || ($p['valid_until'] !== null && $date > $p['valid_until'])) {
                    $error = 'A data está fora da validade do pacote.';
                } elseif ($end->format('Y-m-d') !== $date || !Db::one(
                    "SELECT id FROM trainer_availability WHERE trainer_id = ? AND mode = 'presencial' AND weekday = ? AND start_time <= ? AND end_time >= ?",
                    [$p['trainer_id'], $weekday, $start->format('H:i:s'), $end->format('H:i:s')])) {
                    $error = 'O horário está fora da disponibilidade presencial do treinador.';
                } elseif (self::overlap('trainer_id', (int) $p['trainer_id'], $start, $end, ['approved'])) {
                    $error = 'O treinador já tem sessão confirmada nesse horário.';
                } elseif (self::overlap('athlete_id', $athleteId, $start, $end, ['requested', 'approved'])) {
                    $error = 'Você já tem um pedido ou sessão nesse horário.';
                } elseif ($p['format'] === 'mensal') {
                    $monday = $start->modify('monday this week')->format('Y-m-d 00:00:00');
                    $next = $start->modify('monday next week')->format('Y-m-d 00:00:00');
                    $n = (int) Db::one("SELECT COUNT(*) c FROM pt_sessions WHERE package_id = ? AND starts_at >= ? AND starts_at < ?
                                        AND (status IN ('requested','approved','done') OR (status IN ('missed','cancelled') AND consumes = 1))", [$packageId, $monday, $next])['c'];
                    if ($n >= (int) $p['weekly_frequency']) {
                        $error = 'Você já usou a frequência semanal desta mensalidade.';
                    }
                } else {
                    $b = self::balance($p);
                    if ($b['remaining'] - $b['booked'] <= 0) {
                        $error = 'Não há sessões disponíveis neste pacote.';
                    }
                }
            }
            if ($error !== null) {
                $pdo->rollBack();
                return $error;
            }
            Db::run('INSERT INTO pt_sessions (package_id, athlete_id, trainer_id, starts_at, ends_at) VALUES (?,?,?,?,?)',
                [$packageId, $athleteId, $p['trainer_id'], $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        Audit::log('pt_requested', $athleteId, 'pt_session', $id);
        return null;
    }

    /** @param list<string> $statuses  $column é sempre constante do código */
    private static function overlap(string $column, int $id, \DateTimeImmutable $start, \DateTimeImmutable $end, array $statuses, int $ignore = 0): bool
    {
        $in = "'" . implode("','", $statuses) . "'";
        return Db::one("SELECT id FROM pt_sessions WHERE $column = ? AND status IN ($in) AND starts_at < ? AND ends_at > ? AND id <> ? LIMIT 1",
            [$id, $end->format('Y-m-d H:i:s'), $start->format('Y-m-d H:i:s'), $ignore]) !== null;
    }

    /**
     * Ações do treinador sobre a sessão.
     * approve | refuse (pedido) · done | missed | cancel (confirmada)
     */
    public static function trainerAction(int $trainerId, int $sessionId, string $action, bool $consumes, string $note): ?string
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            Db::one('SELECT id FROM users WHERE id = ? FOR UPDATE', [$trainerId]); // serializa aprovações
            $s = Db::one('SELECT * FROM pt_sessions WHERE id = ? AND trainer_id = ?', [$sessionId, $trainerId]);
            $error = null;
            $status = null;
            $use = 0;
            if (!$s) {
                $error = 'Sessão não encontrada.';
            } elseif ($action === 'approve' || $action === 'refuse') {
                if ($s['status'] !== 'requested') {
                    $error = 'Este pedido já foi respondido.';
                } elseif ($action === 'approve' && self::overlap('trainer_id', $trainerId, new \DateTimeImmutable($s['starts_at']), new \DateTimeImmutable($s['ends_at']), ['approved'], $sessionId)) {
                    $error = 'Você já tem sessão confirmada nesse horário.';
                } else {
                    $status = $action === 'approve' ? 'approved' : 'refused';
                }
            } elseif (in_array($action, ['done', 'missed', 'cancel'], true)) {
                if ($s['status'] !== 'approved') {
                    $error = 'Só uma sessão confirmada pode ser registrada.';
                } elseif ($action !== 'cancel' && $s['starts_at'] > date('Y-m-d H:i:s')) {
                    $error = 'A sessão ainda não aconteceu.';
                } else {
                    $status = ['done' => 'done', 'missed' => 'missed', 'cancel' => 'cancelled'][$action];
                    $use = $action === 'done' || $consumes ? 1 : 0;
                }
            } else {
                $error = 'Ação inválida.';
            }
            if ($error !== null) {
                $pdo->rollBack();
                return $error;
            }
            Db::run('UPDATE pt_sessions SET status = ?, consumes = ?, note = ?, decided_by = ?, decided_at = NOW() WHERE id = ?',
                [$status, $use, trim($note) === '' ? null : mb_substr(trim($note), 0, 300), $trainerId, $sessionId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        Audit::log('pt_' . $status, $trainerId, 'pt_session', $sessionId, ['consumes' => $use]);
        return null;
    }

    /** O atleta só desiste de pedido ainda não respondido (a regra para sessão confirmada não foi definida). */
    public static function athleteCancel(int $athleteId, int $sessionId): ?string
    {
        $st = Db::run("UPDATE pt_sessions SET status = 'cancelled', consumes = 0, decided_by = ?, decided_at = NOW() WHERE id = ? AND athlete_id = ? AND status = 'requested'",
            [$athleteId, $sessionId, $athleteId]);
        if ($st->rowCount() !== 1) {
            return 'Só é possível desistir de um pedido ainda não respondido. Para sessão confirmada, fale com o treinador.';
        }
        Audit::log('pt_cancelled', $athleteId, 'pt_session', $sessionId, ['by' => 'athlete']);
        return null;
    }

    public static function cancelPackage(int $packageId, string $reason, int $adminId): ?string
    {
        if (mb_strlen(trim($reason)) < 3) {
            return 'Informe o motivo.';
        }
        $st = Db::run("UPDATE pt_packages SET status = 'cancelled', cancel_reason = ? WHERE id = ? AND status = 'active'", [mb_substr(trim($reason), 0, 300), $packageId]);
        if ($st->rowCount() !== 1) {
            return 'Este pacote não está ativo.';
        }
        Db::run("UPDATE pt_sessions SET status = 'cancelled', consumes = 0, note = 'Pacote cancelado', decided_by = ?, decided_at = NOW()
                 WHERE package_id = ? AND status IN ('requested','approved') AND starts_at > NOW()", [$adminId, $packageId]);
        Audit::log('pt_package_cancelled', $adminId, 'pt_package', $packageId);
        return null;
    }

    public static function pay(int $packageId, string $paidOn, string $method, int $adminId): ?string
    {
        if (!Billing::validDate($paidOn) || $paidOn > date('Y-m-d') || !isset(Billing::METHODS[$method])) {
            return 'Informe data (não futura) e forma de pagamento.';
        }
        $st = Db::run('UPDATE pt_packages SET paid_on = ?, method = ? WHERE id = ? AND paid_on IS NULL AND price_cents IS NOT NULL', [$paidOn, $method, $packageId]);
        if ($st->rowCount() !== 1) {
            return 'Não há valor em aberto neste pacote.';
        }
        Audit::log('pt_paid', $adminId, 'pt_package', $packageId, ['method' => $method]);
        return null;
    }
}
