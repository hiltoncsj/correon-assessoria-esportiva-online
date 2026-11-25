<?php
declare(strict_types=1);
namespace Correon;

/**
 * Painel do treinador (Sprint 4): início, carteira, acompanhamento, agenda, disponibilidade e novidades.
 * TODA consulta de treino passa pelo vínculo ativo na modalidade (constante LINKED), nunca por id vindo do cliente.
 */
final class TrainerPanel
{
    /** Junta treinos (w) somente aos vínculos ativos do treinador (1º parâmetro) na mesma modalidade. */
    private const LINKED = "workouts w JOIN trainer_athletes ta ON ta.athlete_id = w.athlete_id AND ta.modality_id = w.modality_id
                            AND ta.status = 'active' AND ta.trainer_id = ? JOIN users a ON a.id = w.athlete_id
                            LEFT JOIN workout_logs l ON l.workout_id = w.id";

    public static function home(): never
    {
        $u = App::require('trainer');
        $t = (int) $u['id'];
        $monday = Templates::monday(date('Y-m-d'));
        $sunday = date('Y-m-d', strtotime("$monday +6 days"));
        $stats = Db::one(
            'SELECT
               SUM(w.status = \'published\' AND w.scheduled_date BETWEEN ? AND ?) AS week_published,
               SUM(w.status = \'draft\' AND w.scheduled_date >= ?) AS drafts
             FROM ' . self::LINKED,
            [$monday, $sunday, $monday, $t]
        );
        $withoutWeek = Db::all(
            "SELECT a.id, a.name FROM trainer_athletes ta JOIN users a ON a.id = ta.athlete_id
             WHERE ta.trainer_id = ? AND ta.status = 'active' AND NOT EXISTS (
               SELECT 1 FROM workouts w JOIN trainer_athletes t2 ON t2.athlete_id = w.athlete_id AND t2.modality_id = w.modality_id
                 AND t2.trainer_id = ta.trainer_id AND t2.status = 'active'
               WHERE w.athlete_id = a.id AND w.status <> 'cancelled' AND w.scheduled_date BETWEEN ? AND ?)
             GROUP BY a.id, a.name ORDER BY a.name",
            [$t, $monday, $sunday]
        );
        $missing = Db::all(
            'SELECT w.id, w.title, w.scheduled_date, a.name AS athlete FROM ' . self::LINKED . "
             WHERE w.status = 'published' AND l.id IS NULL AND w.scheduled_date >= (CURDATE() - INTERVAL 7 DAY) AND w.scheduled_date < CURDATE()
             ORDER BY w.scheduled_date DESC, a.name LIMIT 50",
            [$t]
        );
        View::render('trainer/dashboard', [
            'title' => 'Painel do treinador', 'shell' => 'trainer', 'active' => 'inicio',
            'capacity' => Capacity::status($t), 'stats' => $stats, 'withoutWeek' => $withoutWeek, 'missing' => $missing,
            'news' => self::unseen($t), 'monday' => $monday, 'sunday' => $sunday,
            'presential' => ['active' => Personal::activeAthletes($t), 'limit' => Personal::presentialLimit($t)],
            'ptPending' => (int) Db::one("SELECT COUNT(*) c FROM pt_sessions WHERE trainer_id = ? AND status = 'requested'", [$t])['c'],
        ]);
    }

    public static function athletes(): never
    {
        $u = App::require('trainer');
        $t = (int) $u['id'];
        $athletes = Db::all(
            "SELECT a.id, a.name, GROUP_CONCAT(m.name ORDER BY m.id SEPARATOR ', ') AS modalities,
               (SELECT COUNT(*) FROM workouts w WHERE w.athlete_id = a.id AND w.status = 'draft'
                  AND w.modality_id IN (SELECT t2.modality_id FROM trainer_athletes t2 WHERE t2.trainer_id = ? AND t2.athlete_id = a.id AND t2.status = 'active')) AS drafts,
               (SELECT COUNT(*) FROM workouts w WHERE w.athlete_id = a.id AND w.status = 'published' AND w.scheduled_date >= CURDATE()
                  AND w.modality_id IN (SELECT t3.modality_id FROM trainer_athletes t3 WHERE t3.trainer_id = ? AND t3.athlete_id = a.id AND t3.status = 'active')) AS upcoming
             FROM trainer_athletes ta JOIN users a ON a.id = ta.athlete_id JOIN modalities m ON m.id = ta.modality_id
             WHERE ta.trainer_id = ? AND ta.status = 'active' GROUP BY a.id, a.name ORDER BY a.name",
            [$t, $t, $t]
        );
        View::render('trainer/home', ['title' => 'Meus atletas', 'shell' => 'trainer', 'active' => 'home',
            'athletes' => $athletes, 'capacity' => Capacity::status($t)]);
    }

    /** Planejado × realizado no período (últimas 1, 4 ou 12 semanas, até hoje). */
    public static function followUp(): never
    {
        $u = App::require('trainer');
        $t = (int) $u['id'];
        $weeks = (int) ($_GET['semanas'] ?? 1);
        $weeks = in_array($weeks, [1, 4, 12], true) ? $weeks : 1;
        $from = date('Y-m-d', strtotime(Templates::monday(date('Y-m-d')) . ' -' . (($weeks - 1) * 7) . ' days'));
        $rows = Db::all(
            "SELECT a.id, a.name,
               COUNT(*) AS planned,
               SUM(l.outcome = 'completed') AS completed,
               SUM(l.outcome = 'partial') AS partial,
               SUM(l.outcome = 'skipped') AS skipped,
               SUM(l.id IS NULL AND w.scheduled_date < CURDATE()) AS missing,
               ROUND(AVG(l.effort), 1) AS effort
             FROM " . self::LINKED . "
             WHERE w.status = 'published' AND w.scheduled_date BETWEEN ? AND CURDATE()
             GROUP BY a.id, a.name ORDER BY missing DESC, a.name",
            [$t, $from]
        );
        $records = Db::all(
            'SELECT w.id, w.title, a.name AS athlete, l.outcome, l.performed_on, l.effort, l.notes FROM ' . self::LINKED . '
             WHERE l.id IS NOT NULL AND l.performed_on >= ? ORDER BY l.performed_on DESC, l.id DESC LIMIT 30',
            [$t, $from]
        );
        View::render('trainer/followup', ['title' => 'Acompanhamento', 'shell' => 'trainer', 'active' => 'acompanhamento',
            'rows' => $rows, 'records' => $records, 'weeks' => $weeks, 'from' => $from]);
    }

    /** Treinos de todos os atletas na semana, dia a dia. */
    public static function agenda(): never
    {
        $u = App::require('trainer');
        $t = (int) $u['id'];
        $ref = (string) ($_GET['semana'] ?? date('Y-m-d'));
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $ref);
        $monday = Templates::monday($d && $d->format('Y-m-d') === $ref ? $ref : date('Y-m-d'));
        $sunday = date('Y-m-d', strtotime("$monday +6 days"));
        $rows = Db::all(
            'SELECT w.id, w.title, w.scheduled_date, w.status, a.name AS athlete, l.outcome FROM ' . self::LINKED . "
             WHERE w.status <> 'cancelled' AND w.scheduled_date BETWEEN ? AND ? ORDER BY w.scheduled_date, a.name, w.id",
            [$t, $monday, $sunday]
        );
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[date('Y-m-d', strtotime("$monday +$i days"))] = [];
        }
        foreach ($rows as $r) {
            $days[$r['scheduled_date']][] = $r;
        }
        $personal = [];
        foreach (Db::all("SELECT s.starts_at, s.status, a.name AS athlete FROM pt_sessions s JOIN users a ON a.id = s.athlete_id
                          WHERE s.trainer_id = ? AND s.status IN ('approved','done','missed') AND s.starts_at >= ? AND s.starts_at < ? + INTERVAL 1 DAY ORDER BY s.starts_at",
            [$t, $monday, $sunday]) as $s) {
            $personal[substr($s['starts_at'], 0, 10)][] = $s;
        }
        View::render('trainer/agenda', ['personal' => $personal, 'title' => 'Agenda', 'shell' => 'trainer', 'active' => 'agenda', 'days' => $days,
            'monday' => $monday, 'sunday' => $sunday,
            'prev' => date('Y-m-d', strtotime("$monday -7 days")), 'next' => date('Y-m-d', strtotime("$monday +7 days"))]);
    }

    // ---- disponibilidade ----

    public static function availability(): never
    {
        $u = App::require('trainer');
        self::availabilityView((int) $u['id'], ['weekday' => '', 'start_time' => '', 'end_time' => '', 'mode' => 'online'], null, 200);
    }

    public static function availabilityAdd(): never
    {
        $u = App::require('trainer');
        $t = (int) $u['id'];
        $in = ['weekday' => Http::input('weekday'), 'start_time' => Http::input('start_time'), 'end_time' => Http::input('end_time'), 'mode' => Http::input('mode')];
        $time = '/^([01]\d|2[0-3]):[0-5]\d$/';
        $error = null;
        if (!ctype_digit($in['weekday']) || (int) $in['weekday'] < 1 || (int) $in['weekday'] > 7) {
            $error = 'Escolha o dia da semana.';
        } elseif (!preg_match($time, $in['start_time']) || !preg_match($time, $in['end_time']) || $in['start_time'] >= $in['end_time']) {
            $error = 'Informe início e fim válidos (o fim deve ser depois do início).';
        } elseif (!in_array($in['mode'], ['online', 'presencial'], true)) {
            $error = 'Escolha online ou presencial.';
        } elseif (Db::one('SELECT id FROM trainer_availability WHERE trainer_id = ? AND weekday = ? AND start_time < ? AND end_time > ?',
            [$t, (int) $in['weekday'], $in['end_time'], $in['start_time']])) {
            $error = 'Esse horário se sobrepõe a outro já cadastrado no mesmo dia.';
        }
        if ($error !== null) {
            self::availabilityView($t, $in, $error, 422);
        }
        Db::run('INSERT INTO trainer_availability (trainer_id, weekday, start_time, end_time, mode) VALUES (?,?,?,?,?)',
            [$t, (int) $in['weekday'], $in['start_time'], $in['end_time'], $in['mode']]);
        Audit::log('availability_added', $t, 'availability', (int) Db::pdo()->lastInsertId());
        Http::flash('ok', 'Horário adicionado.');
        Http::redirect('/treinador/disponibilidade');
    }

    public static function availabilityRemove(int $id): never
    {
        $u = App::require('trainer');
        $st = Db::run('DELETE FROM trainer_availability WHERE id = ? AND trainer_id = ?', [$id, $u['id']]);
        if ($st->rowCount() !== 1) {
            App::notFound();
        }
        Audit::log('availability_removed', (int) $u['id'], 'availability', $id);
        Http::flash('ok', 'Horário removido.');
        Http::redirect('/treinador/disponibilidade');
    }

    /** @param array<string,string> $old */
    private static function availabilityView(int $trainerId, array $old, ?string $error, int $status): never
    {
        View::render('trainer/availability', ['title' => 'Disponibilidade', 'shell' => 'trainer', 'active' => 'disponibilidade',
            'slots' => Db::all('SELECT id, weekday, start_time, end_time, mode FROM trainer_availability WHERE trainer_id = ? ORDER BY weekday, start_time', [$trainerId]),
            'old' => $old, 'error' => $error], $status);
    }

    // ---- novidades (registros dos atletas) ----

    public static function news(): never
    {
        $u = App::require('trainer');
        $t = (int) $u['id'];
        $seen = Db::one('SELECT news_seen_at FROM trainer_settings WHERE trainer_id = ?', [$t])['news_seen_at'] ?? null;
        $items = Db::all(
            'SELECT w.id, w.title, a.name AS athlete, l.outcome, l.performed_on, l.effort, l.notes, l.updated_at FROM ' . self::LINKED . '
             WHERE l.id IS NOT NULL AND l.updated_at >= (NOW() - INTERVAL 30 DAY) ORDER BY l.updated_at DESC LIMIT 50',
            [$t]
        );
        Db::run('INSERT INTO trainer_settings (trainer_id, news_seen_at) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE news_seen_at = NOW()', [$t]);
        View::render('trainer/news', ['title' => 'Novidades', 'shell' => 'trainer', 'active' => 'novidades', 'items' => $items, 'seen' => $seen]);
    }

    /** Registros de atletas ainda não vistos pelo treinador (últimos 30 dias). */
    public static function unseen(int $trainerId): int
    {
        return (int) (Db::one(
            'SELECT COUNT(*) c FROM ' . self::LINKED . "
             WHERE l.id IS NOT NULL AND l.updated_at >= (NOW() - INTERVAL 30 DAY)
               AND l.updated_at > COALESCE((SELECT news_seen_at FROM trainer_settings WHERE trainer_id = ?), '1970-01-01')",
            [$trainerId, $trainerId]
        )['c'] ?? 0);
    }
}
