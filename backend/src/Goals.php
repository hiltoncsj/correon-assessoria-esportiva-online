<?php
declare(strict_types=1);
namespace Correon;

/**
 * Objetivos e provas: uma lista única da equipe. Qualquer treinador adiciona, edita e remove;
 * cada atleta coloca ou tira o próprio nome. O admin só consulta.
 * Treinador vê o total de inscritos, mas os NOMES só dos atletas com vínculo ativo com ele.
 * Remover não apaga: o objetivo e as inscrições ficam no histórico do banco.
 */
final class Goals
{
    public const KINDS = ['prova' => 'Prova', 'objetivo' => 'Objetivo'];
    public const SPORTS = ['corrida' => 'Corrida', 'triathlon' => 'Triathlon', 'natacao' => 'Natação', 'ciclismo' => 'Ciclismo', 'trail' => 'Trail', 'outro' => 'Outro'];
    private const EMPTY = ['kind' => 'prova', 'sport' => '', 'name' => '', 'target_date' => '', 'distance' => '', 'city' => '', 'details' => ''];

    /** Total exibido no site: objetivos cadastrados e não removidos. */
    public static function publicCount(): int
    {
        return (int) Db::one('SELECT COUNT(*) c FROM goals WHERE removed_at IS NULL')['c'];
    }

    /** Objetivos em que o atleta está (removidos pela equipe não aparecem). @return list<array<string,mixed>> */
    public static function ofAthlete(int $athleteId): array
    {
        return Db::all(
            'SELECT g.*, p.target, p.result FROM goal_participants p JOIN goals g ON g.id = p.goal_id
             WHERE p.athlete_id = ? AND p.left_at IS NULL AND g.removed_at IS NULL ORDER BY g.target_date IS NULL, g.target_date, g.id',
            [$athleteId]
        );
    }

    /** Lista ativa separada em próximos (sem data ou de hoje em diante) e anteriores. @return array{next:list<array<string,mixed>>,past:list<array<string,mixed>>} */
    private static function listed(int $athleteId = 0): array
    {
        $rows = Db::all(
            'SELECT g.*, me.athlete_id IS NOT NULL AS joined, me.target, me.result,
                    (SELECT COUNT(*) FROM goal_participants p JOIN users u ON u.id = p.athlete_id AND u.status = \'active\'
                      WHERE p.goal_id = g.id AND p.left_at IS NULL) AS total
             FROM goals g LEFT JOIN goal_participants me ON me.goal_id = g.id AND me.athlete_id = ? AND me.left_at IS NULL
             WHERE g.removed_at IS NULL ORDER BY g.target_date IS NULL, g.target_date, g.id',
            [$athleteId]
        );
        $out = ['next' => [], 'past' => []];
        $today = date('Y-m-d');
        foreach ($rows as $g) {
            $out[$g['target_date'] === null || $g['target_date'] >= $today ? 'next' : 'past'][] = $g;
        }
        $out['past'] = array_reverse($out['past']);
        return $out;
    }

    /** @return array<string,mixed> objetivo ativo, ou 404 */
    private static function active(int $id): array
    {
        $g = Db::one('SELECT * FROM goals WHERE id = ? AND removed_at IS NULL', [$id]);
        if (!$g) {
            App::notFound();
        }
        return $g;
    }

    /** @return array{0:array<string,string>,1:?string} valores e erro */
    private static function posted(): array
    {
        $v = [];
        foreach (array_keys(self::EMPTY) as $k) {
            $v[$k] = trim(Http::input($k));
        }
        $err = null;
        if (!isset(self::KINDS[$v['kind']]) || !isset(self::SPORTS[$v['sport']])) {
            $err = 'Escolha o tipo e o esporte.';
        } elseif (mb_strlen($v['name']) < 2 || mb_strlen($v['name']) > 150) {
            $err = 'Informe o nome (2 a 150 caracteres).';
        } elseif ($v['target_date'] !== '' && !self::validDate($v['target_date'])) {
            $err = 'Data inválida.';
        } elseif ($v['kind'] === 'prova' && $v['target_date'] === '') {
            $err = 'Informe a data da prova.';
        } else {
            foreach (['distance' => [60, 'Distância'], 'city' => [100, 'Cidade'], 'details' => [500, 'Observações']] as $k => [$max, $label]) {
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

    /** @param array<string,string> $v  @return list<?string> */
    private static function columns(array $v): array
    {
        $n = static fn (string $s) => $s === '' ? null : $s;
        return [$v['kind'], $v['sport'], $v['name'], $n($v['target_date']), $n($v['distance']), $n($v['city']), $n($v['details'])];
    }

    // ---- atleta ----

    public static function athleteList(): never
    {
        $u = App::require('athlete');
        View::render('goals/athlete', ['title' => 'Objetivos e provas', 'shell' => 'athlete', 'active' => 'objetivos', 'goals' => self::listed((int) $u['id'])]);
    }

    public static function join(int $goalId): never
    {
        $u = App::require('athlete');
        self::active($goalId);
        Db::run('INSERT INTO goal_participants (goal_id, athlete_id) VALUES (?,?) ON DUPLICATE KEY UPDATE left_at = NULL', [$goalId, $u['id']]);
        Audit::log('goal_joined', (int) $u['id'], 'goal', $goalId);
        Http::flash('ok', 'Seu nome foi adicionado.');
        Http::redirect('/app/objetivos');
    }

    public static function leave(int $goalId): never
    {
        $u = App::require('athlete');
        self::active($goalId);
        Db::run('UPDATE goal_participants SET left_at = NOW() WHERE goal_id = ? AND athlete_id = ? AND left_at IS NULL', [$goalId, $u['id']]);
        Audit::log('goal_left', (int) $u['id'], 'goal', $goalId);
        Http::flash('ok', 'Seu nome foi retirado.');
        Http::redirect('/app/objetivos');
    }

    /** Meta e resultado pessoais do atleta inscrito. */
    public static function mine(int $goalId): never
    {
        $u = App::require('athlete');
        self::active($goalId);
        if (!Db::one('SELECT 1 ok FROM goal_participants WHERE goal_id = ? AND athlete_id = ? AND left_at IS NULL', [$goalId, $u['id']])) {
            App::notFound();
        }
        $target = trim(Http::input('target'));
        $result = trim(Http::input('result'));
        if (mb_strlen($target) > 150 || mb_strlen($result) > 500) {
            Http::flash('err', 'Meta: até 150 caracteres. Resultado: até 500.');
        } else {
            Db::run('UPDATE goal_participants SET target = ?, result = ? WHERE goal_id = ? AND athlete_id = ?',
                [$target === '' ? null : $target, $result === '' ? null : $result, $goalId, $u['id']]);
            Http::flash('ok', 'Meta e resultado salvos.');
        }
        Http::redirect('/app/objetivos');
    }

    // ---- treinador ----

    /** @param array<string,string> $old */
    private static function renderTrainer(array $old, ?string $error, int $status): never
    {
        View::render('goals/trainer', ['title' => 'Objetivos e provas', 'shell' => 'trainer', 'active' => 'objetivos',
            'goals' => self::listed(), 'old' => $old, 'error' => $error], $status);
    }

    public static function trainerList(): never
    {
        App::require('trainer');
        self::renderTrainer(self::EMPTY, null, 200);
    }

    public static function create(): never
    {
        $u = App::require('trainer');
        [$v, $err] = self::posted();
        if ($err !== null) {
            self::renderTrainer($v, $err, 422);
        }
        Db::run('INSERT INTO goals (created_by, kind, sport, name, target_date, distance, city, details) VALUES (?,?,?,?,?,?,?,?)',
            [$u['id'], ...self::columns($v)]);
        Audit::log('goal_created', (int) $u['id'], 'goal', (int) Db::pdo()->lastInsertId());
        Http::flash('ok', 'Cadastrado.');
        Http::redirect('/treinador/objetivos');
    }

    /** @param array<string,mixed> $values */
    private static function renderEdit(int $trainerId, int $goalId, array $values, ?string $error, int $status): never
    {
        $mine = Db::all(
            "SELECT u.id, u.name, p.target, p.result FROM goal_participants p JOIN users u ON u.id = p.athlete_id AND u.status = 'active'
             WHERE p.goal_id = ? AND p.left_at IS NULL
               AND EXISTS (SELECT 1 FROM trainer_athletes ta WHERE ta.athlete_id = u.id AND ta.trainer_id = ? AND ta.status = 'active')
             ORDER BY u.name",
            [$goalId, $trainerId]
        );
        $total = (int) Db::one("SELECT COUNT(*) c FROM goal_participants p JOIN users u ON u.id = p.athlete_id AND u.status = 'active'
                                WHERE p.goal_id = ? AND p.left_at IS NULL", [$goalId])['c'];
        View::render('goals/edit', ['title' => 'Objetivo ou prova', 'shell' => 'trainer', 'active' => 'objetivos', 'goalId' => $goalId,
            'old' => array_map('strval', array_intersect_key($values, self::EMPTY) + self::EMPTY), 'error' => $error,
            'participants' => $mine, 'others' => $total - count($mine)], $status);
    }

    public static function edit(int $goalId): never
    {
        $u = App::require('trainer');
        self::renderEdit((int) $u['id'], $goalId, self::active($goalId), null, 200);
    }

    public static function update(int $goalId): never
    {
        $u = App::require('trainer');
        self::active($goalId);
        [$v, $err] = self::posted();
        if ($err !== null) {
            self::renderEdit((int) $u['id'], $goalId, $v, $err, 422);
        }
        Db::run('UPDATE goals SET kind = ?, sport = ?, name = ?, target_date = ?, distance = ?, city = ?, details = ? WHERE id = ?',
            [...self::columns($v), $goalId]);
        Audit::log('goal_updated', (int) $u['id'], 'goal', $goalId);
        Http::flash('ok', 'Alterações salvas.');
        Http::redirect('/treinador/objetivos');
    }

    public static function remove(int $goalId): never
    {
        $u = App::require('trainer');
        self::active($goalId);
        Db::run('UPDATE goals SET removed_at = NOW(), removed_by = ? WHERE id = ?', [$u['id'], $goalId]);
        Audit::log('goal_removed', (int) $u['id'], 'goal', $goalId);
        Http::flash('ok', 'Removido da lista.');
        Http::redirect('/treinador/objetivos');
    }

    /** Objetivos de um atleta, para o treinador vinculado. */
    public static function trainerAthlete(int $athleteId): never
    {
        $u = App::require('trainer');
        if (!Workout::linkedModalities((int) $u['id'], $athleteId)) {
            App::notFound();
        }
        $athlete = Db::one('SELECT id, name FROM users WHERE id = ?', [$athleteId]);
        View::render('goals/of_athlete', ['title' => 'Objetivos de ' . $athlete['name'], 'shell' => 'trainer', 'active' => 'objetivos',
            'athlete' => $athlete, 'items' => self::ofAthlete($athleteId)]);
    }
}
