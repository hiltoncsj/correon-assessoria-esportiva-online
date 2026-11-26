<?php
declare(strict_types=1);
namespace Correon;

/**
 * Objetivos e provas. O atleta cuida dos próprios; o treinador, dos atletas com vínculo ativo
 * (qualquer modalidade); o admin só consulta. Sem vínculo, o recurso responde 404.
 */
final class Goals
{
    public const KINDS = ['prova' => 'Prova', 'objetivo' => 'Objetivo'];
    public const SPORTS = ['corrida' => 'Corrida', 'triathlon' => 'Triathlon', 'natacao' => 'Natação', 'ciclismo' => 'Ciclismo', 'trail' => 'Trail', 'outro' => 'Outro'];
    public const STATUSES = ['planned' => 'Planejado', 'done' => 'Concluído', 'cancelled' => 'Cancelado'];
    private const MAX_PER_ATHLETE = 200;
    private const EMPTY = ['kind' => 'prova', 'sport' => '', 'name' => '', 'target_date' => '', 'distance' => '', 'city' => '', 'target' => '', 'status' => 'planned', 'result' => ''];

    /** Total exibido no site: só atletas ativos e vinculados a um treinador (cadastro avulso não conta). */
    public static function publicCount(): int
    {
        return (int) Db::one(
            "SELECT COUNT(*) c FROM athlete_goals g JOIN users u ON u.id = g.athlete_id AND u.status = 'active'
             WHERE g.status <> 'cancelled' AND EXISTS (SELECT 1 FROM trainer_athletes ta WHERE ta.athlete_id = g.athlete_id AND ta.status = 'active')"
        )['c'];
    }

    /** Próximos (planejados, sem data ou de hoje em diante) primeiro; o restante vira histórico. @return array{next:list<array<string,mixed>>,past:list<array<string,mixed>>} */
    public static function ofAthlete(int $athleteId): array
    {
        $out = ['next' => [], 'past' => []];
        $today = date('Y-m-d');
        foreach (Db::all('SELECT * FROM athlete_goals WHERE athlete_id = ? ORDER BY target_date IS NULL, target_date, id', [$athleteId]) as $g) {
            $out[$g['status'] === 'planned' && ($g['target_date'] === null || $g['target_date'] >= $today) ? 'next' : 'past'][] = $g;
        }
        $out['past'] = array_reverse($out['past']);
        return $out;
    }

    /** @return array{0:array<string,string>,1:?string} valores e erro */
    private static function posted(): array
    {
        $v = [];
        foreach (array_keys(self::EMPTY) as $k) {
            $v[$k] = trim(Http::input($k));
        }
        $err = null;
        if (!isset(self::KINDS[$v['kind']]) || !isset(self::SPORTS[$v['sport']]) || !isset(self::STATUSES[$v['status']])) {
            $err = 'Escolha o tipo, o esporte e a situação.';
        } elseif (mb_strlen($v['name']) < 2 || mb_strlen($v['name']) > 150) {
            $err = 'Informe o nome (2 a 150 caracteres).';
        } elseif ($v['target_date'] !== '' && !self::validDate($v['target_date'])) {
            $err = 'Data inválida.';
        } elseif ($v['kind'] === 'prova' && $v['target_date'] === '') {
            $err = 'Informe a data da prova.';
        } else {
            foreach (['distance' => [60, 'Distância'], 'city' => [100, 'Cidade'], 'target' => [150, 'Meta'], 'result' => [500, 'Resultado']] as $k => [$max, $label]) {
                if (mb_strlen($v[$k]) > $max) {
                    $err = "$label: no máximo $max caracteres.";
                    break;
                }
            }
        }
        return [$v, $err];
    }

    private static function validDate(string $d): bool
    {
        $p = \DateTimeImmutable::createFromFormat('!Y-m-d', $d);
        return $p !== false && $p->format('Y-m-d') === $d && $d >= '2000-01-01' && $d <= '2100-12-31';
    }

    /** @param array<string,string> $v  @return list<mixed> */
    private static function columns(array $v): array
    {
        $n = static fn (string $s) => $s === '' ? null : $s;
        return [$v['kind'], $v['sport'], $v['name'], $n($v['target_date']), $n($v['distance']), $n($v['city']), $n($v['target']), $v['status'], $n($v['result'])];
    }

    /** @param array<string,string> $v  @return string|null erro */
    private static function insert(int $athleteId, int $by, array $v): ?string
    {
        if ((int) Db::one('SELECT COUNT(*) c FROM athlete_goals WHERE athlete_id = ?', [$athleteId])['c'] >= self::MAX_PER_ATHLETE) {
            return 'Limite de ' . self::MAX_PER_ATHLETE . ' registros atingido.';
        }
        Db::run('INSERT INTO athlete_goals (athlete_id, created_by, kind, sport, name, target_date, distance, city, target, status, result) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [$athleteId, $by, ...self::columns($v)]);
        Audit::log('goal_created', $by, 'user', $athleteId);
        return null;
    }

    /** @param array<string,mixed> $goal @param array<string,string> $v */
    private static function save(array $goal, int $by, array $v): void
    {
        Db::run('UPDATE athlete_goals SET kind = ?, sport = ?, name = ?, target_date = ?, distance = ?, city = ?, target = ?, status = ?, result = ? WHERE id = ?',
            [...self::columns($v), $goal['id']]);
        Audit::log('goal_updated', $by, 'user', (int) $goal['athlete_id']);
    }

    // ---- atleta ----

    public static function athleteList(): never
    {
        $u = App::require('athlete');
        self::renderAthlete((int) $u['id'], self::EMPTY, null, 200);
    }

    /** @param array<string,string> $old */
    private static function renderAthlete(int $id, array $old, ?string $error, int $status): never
    {
        View::render('goals/list', ['title' => 'Objetivos e provas', 'shell' => 'athlete', 'active' => 'objetivos', 'label' => 'Atleta',
            'goals' => self::ofAthlete($id), 'base' => '/app/objetivos', 'action' => '/app/objetivos', 'old' => $old, 'error' => $error,
            'note' => 'Seus treinadores e a administração veem estes dados. No site aparece apenas o número total de objetivos da equipe.'], $status);
    }

    public static function athleteCreate(): never
    {
        $u = App::require('athlete');
        $id = (int) $u['id'];
        [$v, $err] = self::posted();
        $err ??= self::insert($id, $id, $v);
        if ($err !== null) {
            self::renderAthlete($id, $v, $err, 422);
        }
        Http::flash('ok', 'Cadastrado.');
        Http::redirect('/app/objetivos');
    }

    /** @return array<string,mixed> */
    private static function own(int $goalId, int $athleteId): array
    {
        $g = Db::one('SELECT * FROM athlete_goals WHERE id = ? AND athlete_id = ?', [$goalId, $athleteId]);
        if (!$g) {
            App::notFound();
        }
        return $g;
    }

    public static function athleteEdit(int $goalId): never
    {
        $u = App::require('athlete');
        $g = self::own($goalId, (int) $u['id']);
        self::renderEdit('athlete', '/app/objetivos', "/app/objetivos/$goalId", $g, null, 200);
    }

    public static function athleteUpdate(int $goalId): never
    {
        $u = App::require('athlete');
        $g = self::own($goalId, (int) $u['id']);
        [$v, $err] = self::posted();
        if ($err !== null) {
            self::renderEdit('athlete', '/app/objetivos', "/app/objetivos/$goalId", $v, $err, 422);
        }
        self::save($g, (int) $u['id'], $v);
        Http::flash('ok', 'Alterações salvas.');
        Http::redirect('/app/objetivos');
    }

    /** @param array<string,mixed> $values */
    private static function renderEdit(string $shell, string $back, string $action, array $values, ?string $error, int $status): never
    {
        View::render('goals/edit', ['title' => 'Editar objetivo ou prova', 'shell' => $shell, 'active' => 'objetivos', 'back' => $back,
            'action' => $action, 'old' => array_map('strval', array_intersect_key($values, self::EMPTY) + self::EMPTY), 'error' => $error], $status);
    }

    // ---- treinador ----

    /** Exige vínculo ativo. @return array{0:int,1:array<string,mixed>} treinador e atleta */
    private static function linked(int $athleteId): array
    {
        $u = App::require('trainer');
        if (!Workout::linkedModalities((int) $u['id'], $athleteId)) {
            App::notFound();
        }
        return [(int) $u['id'], Db::one('SELECT id, name FROM users WHERE id = ?', [$athleteId])];
    }

    public static function trainerUpcoming(): never
    {
        $u = App::require('trainer');
        $rows = Db::all(
            "SELECT g.*, a.name AS athlete FROM athlete_goals g JOIN users a ON a.id = g.athlete_id
             WHERE g.status = 'planned' AND (g.target_date IS NULL OR g.target_date >= ?)
               AND EXISTS (SELECT 1 FROM trainer_athletes ta WHERE ta.athlete_id = g.athlete_id AND ta.trainer_id = ? AND ta.status = 'active')
             ORDER BY g.target_date IS NULL, g.target_date, a.name LIMIT 200",
            [date('Y-m-d'), $u['id']]
        );
        View::render('goals/upcoming', ['title' => 'Objetivos e provas', 'shell' => 'trainer', 'active' => 'objetivos', 'rows' => $rows]);
    }

    public static function trainerList(int $athleteId): never
    {
        [, $athlete] = self::linked($athleteId);
        self::renderTrainer($athlete, self::EMPTY, null, 200);
    }

    /** @param array<string,mixed> $athlete @param array<string,string> $old */
    private static function renderTrainer(array $athlete, array $old, ?string $error, int $status): never
    {
        $id = (int) $athlete['id'];
        View::render('goals/list', ['title' => 'Objetivos de ' . $athlete['name'], 'shell' => 'trainer', 'active' => 'objetivos', 'label' => (string) $athlete['name'],
            'goals' => self::ofAthlete($id), 'base' => '/treinador/objetivos', 'action' => "/treinador/atletas/$id/objetivos", 'old' => $old, 'error' => $error,
            'note' => 'O atleta também vê e edita estes registros.', 'back' => "/treinador/atletas/$id"], $status);
    }

    public static function trainerCreate(int $athleteId): never
    {
        [$me, $athlete] = self::linked($athleteId);
        [$v, $err] = self::posted();
        $err ??= self::insert($athleteId, $me, $v);
        if ($err !== null) {
            self::renderTrainer($athlete, $v, $err, 422);
        }
        Http::flash('ok', 'Cadastrado para o atleta.');
        Http::redirect("/treinador/atletas/$athleteId/objetivos");
    }

    /** @return array{0:int,1:array<string,mixed>} */
    private static function trainerGoal(int $goalId): array
    {
        App::require('trainer');
        $g = Db::one('SELECT * FROM athlete_goals WHERE id = ?', [$goalId]);
        if (!$g) {
            App::notFound();
        }
        [$me] = self::linked((int) $g['athlete_id']);
        return [$me, $g];
    }

    public static function trainerEdit(int $goalId): never
    {
        [, $g] = self::trainerGoal($goalId);
        self::renderEdit('trainer', "/treinador/atletas/{$g['athlete_id']}/objetivos", "/treinador/objetivos/$goalId", $g, null, 200);
    }

    public static function trainerUpdate(int $goalId): never
    {
        [$me, $g] = self::trainerGoal($goalId);
        $back = "/treinador/atletas/{$g['athlete_id']}/objetivos";
        [$v, $err] = self::posted();
        if ($err !== null) {
            self::renderEdit('trainer', $back, "/treinador/objetivos/$goalId", $v, $err, 422);
        }
        self::save($g, $me, $v);
        Http::flash('ok', 'Alterações salvas.');
        Http::redirect($back);
    }
}
