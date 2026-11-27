<?php
declare(strict_types=1);
namespace Correon;

/**
 * Lembrete por e-mail no dia seguinte ao fim da prova, para todos os atletas inscritos.
 * Disparado por tarefa agendada em /api/v1/cron/lembretes. Cada atleta recebe uma única vez por prova.
 * Se a tarefa falhar num dia, as execuções seguintes alcançam provas de até 7 dias atrás.
 */
final class Reminders
{
    private const WINDOW_DAYS = 7;
    private const BATCH = 100;

    /** @return array{enviados:int,falhas:int} */
    public static function run(): array
    {
        $rows = Db::all(
            "SELECT p.goal_id, p.athlete_id, p.result, g.name, g.target_date, g.end_date, g.distance, u.name AS athlete, u.email
             FROM goal_participants p
             JOIN goals g ON g.id = p.goal_id AND g.removed_at IS NULL AND g.kind = 'prova'
             JOIN users u ON u.id = p.athlete_id AND u.status = 'active'
             WHERE p.left_at IS NULL AND p.reminded_at IS NULL AND COALESCE(g.end_date, g.target_date) BETWEEN ? AND ?
             ORDER BY COALESCE(g.end_date, g.target_date), p.goal_id, p.athlete_id LIMIT " . self::BATCH,
            [date('Y-m-d', strtotime('-' . self::WINDOW_DAYS . ' days')), date('Y-m-d', strtotime('-1 day'))]
        );
        $out = ['enviados' => 0, 'falhas' => 0];
        foreach ($rows as $r) {
            $key = [$r['goal_id'], $r['athlete_id']];
            // reserva antes de enviar: duas execuções simultâneas não mandam em dobro
            if (Db::run('UPDATE goal_participants SET reminded_at = NOW() WHERE goal_id = ? AND athlete_id = ? AND reminded_at IS NULL', $key)->rowCount() !== 1) {
                continue;
            }
            if (self::send($r)) {
                $out['enviados']++;
                Notify::send((int) $r['athlete_id'], 'Como foi a prova?', 'Registre o seu resultado em ' . $r['name'] . '.', '/app/objetivos');
            } else {
                $out['falhas']++;
                Db::run('UPDATE goal_participants SET reminded_at = NULL WHERE goal_id = ? AND athlete_id = ?', $key); // tenta de novo depois
            }
        }
        if ($rows) {
            Audit::log('goal_reminders_sent', null, 'goal', null, $out);
        }
        return $out;
    }

    /** @param array<string,mixed> $r */
    private static function send(array $r): bool
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $r['athlete']) ?? '');
        $race = trim(preg_replace('/\s+/u', ' ', (string) $r['name']) ?? '');
        $data = [
            'first' => explode(' ', $name)[0],
            'race' => $race,
            'date' => View::dateBr((string) $r['target_date']) . ($r['end_date'] && $r['end_date'] !== $r['target_date'] ? ' a ' . View::dateBr((string) $r['end_date']) : ''),
            'distance' => (string) $r['distance'],
            'filled' => (string) $r['result'] !== '',
            'url' => rtrim((string) Env::get('APP_URL', ''), '/') . '/app/objetivos',
        ];
        return Mailer::sendHtml(
            (string) $r['email'],
            "Como foi a prova $race? Registre o seu resultado",
            self::render('race_result.txt.php', $data),
            self::render('race_result.php', $data),
            BASE_PATH . '/views/mail/logo-correon.png'
        );
    }

    /** @param array<string,mixed> $data */
    private static function render(string $view, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require BASE_PATH . '/views/mail/' . $view;
        return (string) ob_get_clean();
    }
}
