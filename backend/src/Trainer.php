<?php
declare(strict_types=1);
namespace Correon;

/**
 * Painel do treinador (Sprint 3: lista de atletas e prescrição de treinos).
 * Autorização: o treinador só enxerga atleta/treino com vínculo ATIVO na modalidade correspondente.
 * Recurso sem vínculo responde 404 (não revela que existe).
 */
final class Trainer
{
    public static function home(): never
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
        View::render('trainer/home', ['title' => 'Painel do treinador', 'shell' => 'trainer', 'active' => 'home', 'athletes' => $athletes]);
    }

    public static function athlete(int $athleteId): never
    {
        $u = App::require('trainer');
        $mods = Workout::linkedModalities((int) $u['id'], $athleteId);
        if (!$mods) {
            App::notFound();
        }
        $athlete = Db::one('SELECT id, name FROM users WHERE id = ?', [$athleteId]);
        $in = implode(',', array_fill(0, count($mods), '?'));
        $workouts = Db::all(
            "SELECT w.id, w.title, w.scheduled_date, w.status, w.discipline, m.name AS modality, l.outcome
             FROM workouts w JOIN modalities m ON m.id = w.modality_id LEFT JOIN workout_logs l ON l.workout_id = w.id
             WHERE w.athlete_id = ? AND m.slug IN ($in) ORDER BY w.scheduled_date DESC, w.id DESC LIMIT 100",
            [$athleteId, ...$mods]
        );
        View::render('trainer/athlete', [
            'title' => $athlete['name'], 'shell' => 'trainer', 'active' => 'home',
            'athlete' => $athlete, 'mods' => $mods, 'workouts' => $workouts,
        ]);
    }

    public static function newForm(): never
    {
        $u = App::require('trainer');
        $athleteId = (int) ($_GET['atleta'] ?? 0);
        $mod = (string) ($_GET['modalidade'] ?? '');
        [$athlete] = self::authorizeNew($u, $athleteId, $mod);
        self::form($athlete, $mod, null, [
            'title' => '', 'scheduled_date' => date('Y-m-d'), 'notes' => '', 'discipline' => '',
        ], [], [], 200);
    }

    public static function create(): never
    {
        $u = App::require('trainer');
        $athleteId = (int) ($_POST['athlete_id'] ?? 0);
        $mod = Http::input('modality');
        [$athlete] = self::authorizeNew($u, $athleteId, $mod);

        $action = Http::input('action') === 'publish' ? 'publish' : 'draft';
        [$data, $errors, $items] = self::readForm($mod, null);
        if ($action === 'publish' && !$items && !$errors) {
            $errors['items'] = 'Adicione pelo menos uma linha para publicar.';
        }
        if ($errors) {
            self::form($athlete, $mod, null, $data, self::rawItems(), $errors, 422);
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            Db::run(
                'INSERT INTO workouts (athlete_id, modality_id, discipline, scheduled_date, title, notes, status, published_at, created_by, updated_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$athleteId, Workout::modalityId($mod), $data['discipline'], $data['scheduled_date'], $data['title'],
                    $data['notes'] === '' ? null : $data['notes'], $action === 'publish' ? 'published' : 'draft',
                    $action === 'publish' ? date('Y-m-d H:i:s') : null, $u['id'], $u['id']]
            );
            $id = (int) $pdo->lastInsertId();
            Workout::replaceItems($id, $items);
            Workout::snapshot($id, $action === 'publish' ? 'published' : 'created', (int) $u['id']);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        Audit::log($action === 'publish' ? 'workout_published' : 'workout_created', (int) $u['id'], 'workout', $id, ['athlete_id' => $athleteId]);
        Http::flash('ok', $action === 'publish' ? 'Treino publicado para o atleta.' : 'Rascunho salvo.');
        Http::redirect('/treinador/treinos/' . $id);
    }

    public static function edit(int $id): never
    {
        $u = App::require('trainer');
        $w = self::authorized($u, $id);
        $athlete = ['id' => $w['athlete_id'], 'name' => $w['athlete_name']];
        self::form($athlete, $w['modality'], $w, [
            'title' => $w['title'], 'scheduled_date' => $w['scheduled_date'], 'notes' => (string) $w['notes'], 'discipline' => $w['discipline'],
        ], Workout::items($id), [], 200);
    }

    public static function update(int $id): never
    {
        $u = App::require('trainer');
        $w = self::authorized($u, $id);
        $athlete = ['id' => $w['athlete_id'], 'name' => $w['athlete_name']];
        $action = Http::input('action');
        if (!in_array($action, ['save', 'publish', 'unpublish', 'cancel'], true)) {
            App::notFound();
        }

        $locked = null;
        if ($w['status'] === 'cancelled') {
            $locked = 'Este treino foi cancelado e não pode mais ser alterado.';
        } elseif (Workout::log($id)) {
            $locked = 'O atleta já registrou este treino. Para preservar o histórico, ele não pode mais ser alterado.';
        }
        if ($locked !== null) {
            Http::flash('err', $locked);
            Http::redirect('/treinador/treinos/' . $id);
        }
        if ((int) Http::input('version') !== (int) $w['version']) {
            Http::flash('err', 'Este treino foi alterado por outra pessoa enquanto você editava. Revise e salve novamente.');
            Http::redirect('/treinador/treinos/' . $id);
        }

        $status = $w['status'];
        $note = 'updated';
        $data = ['title' => $w['title'], 'scheduled_date' => $w['scheduled_date'], 'notes' => (string) $w['notes'], 'discipline' => $w['discipline']];
        $items = null;

        if ($action === 'cancel') {
            $status = 'cancelled';
            $note = 'cancelled';
        } elseif ($action === 'unpublish') {
            if ($w['status'] !== 'published') {
                App::notFound();
            }
            $status = 'draft';
            $note = 'unpublished';
        } else {
            [$data, $errors, $items] = self::readForm($w['modality'], $w['discipline']);
            if ($action === 'publish' && !$items && !$errors) {
                $errors['items'] = 'Adicione pelo menos uma linha para publicar.';
            }
            if ($errors) {
                self::form($athlete, $w['modality'], $w, $data, self::rawItems(), $errors, 422);
            }
            if ($action === 'publish') {
                $status = 'published';
                $note = $w['status'] === 'published' ? 'updated' : 'published';
            }
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            Db::run(
                'UPDATE workouts SET title = ?, scheduled_date = ?, notes = ?, status = ?, version = version + 1, updated_by = ?,
                   published_at = IF(? = \'published\' AND published_at IS NULL, NOW(), published_at)
                 WHERE id = ? AND version = ?',
                [$data['title'], $data['scheduled_date'], $data['notes'] === '' ? null : $data['notes'], $status, $u['id'], $status, $id, $w['version']]
            );
            if ($items !== null) {
                Workout::replaceItems($id, $items);
            }
            Workout::snapshot($id, $note, (int) $u['id']);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        Audit::log('workout_' . $note, (int) $u['id'], 'workout', $id, ['athlete_id' => (int) $w['athlete_id']]);
        Http::flash('ok', ['updated' => 'Treino salvo.', 'published' => 'Treino publicado para o atleta.',
            'unpublished' => 'Treino voltou a rascunho (o atleta não o vê).', 'cancelled' => 'Treino cancelado.'][$note]);
        Http::redirect('/treinador/treinos/' . $id);
    }

    // ---- auxiliares ----

    /** @return array{0:array<string,mixed>} */
    private static function authorizeNew(array $u, int $athleteId, string $mod): array
    {
        if (!in_array($mod, Workout::linkedModalities((int) $u['id'], $athleteId), true)) {
            App::notFound();
        }
        $athlete = Db::one('SELECT id, name FROM users WHERE id = ?', [$athleteId]);
        return [$athlete];
    }

    /** Treino do atleta numa modalidade em que ESTE treinador tem vínculo ativo; senão 404. @return array<string,mixed> */
    private static function authorized(array $u, int $id): array
    {
        $w = Workout::find($id);
        if (!$w || !in_array($w['modality'], Workout::linkedModalities((int) $u['id'], (int) $w['athlete_id']), true)) {
            App::notFound();
        }
        return $w;
    }

    /** @return array{0:array<string,string>,1:array<string,string>,2:list<array<string,mixed>>} */
    private static function readForm(string $modality, ?string $fixedDiscipline): array
    {
        $in = [];
        foreach (['title', 'scheduled_date', 'notes', 'discipline'] as $k) {
            $in[$k] = Http::input($k);
        }
        $allowed = Workout::MODALITY_DISCIPLINES[$modality];
        if ($fixedDiscipline === null && count($allowed) === 1) {
            $fixedDiscipline = $allowed[0];
        }
        [$data, $errors] = Workout::validateHeader($in, $modality, $fixedDiscipline);
        [$items, $itemErrors] = Workout::parseItems($_POST['items'] ?? null, $data['discipline']);
        if ($itemErrors) {
            $errors['items'] = implode(' ', $itemErrors);
        }
        return [$data, $errors, $items];
    }

    /** Devolve as linhas digitadas para reexibir o formulário com erro. @return list<array<string,mixed>> */
    private static function rawItems(): array
    {
        $out = [];
        foreach (is_array($_POST['items'] ?? null) ? $_POST['items'] : [] as $row) {
            if (is_array($row)) {
                $out[] = array_map(static fn ($v) => is_string($v) ? $v : '', $row);
            }
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $athlete
     * @param array<string,mixed>|null $w
     * @param array<string,string> $vals
     * @param list<array<string,mixed>> $items
     * @param array<string,string> $errors
     */
    private static function form(array $athlete, string $modality, ?array $w, array $vals, array $items, array $errors, int $status): never
    {
        $disciplines = Workout::MODALITY_DISCIPLINES[$modality];
        $discipline = $vals['discipline'] !== '' ? $vals['discipline'] : $disciplines[0];
        $log = $w ? Workout::log((int) $w['id']) : null;
        View::render('trainer/workout_form', [
            'title' => $w ? 'Treino' : 'Novo treino', 'shell' => 'trainer', 'active' => 'home',
            'athlete' => $athlete, 'modality' => $modality, 'modalityName' => Db::one('SELECT name FROM modalities WHERE slug = ?', [$modality])['name'] ?? '',
            'w' => $w, 'vals' => $vals, 'items' => $items, 'errors' => $errors,
            'disciplines' => $disciplines, 'discipline' => $discipline,
            'log' => $log, 'versions' => $w ? Workout::versions((int) $w['id']) : [],
            'editable' => !$w || ($w['status'] !== 'cancelled' && !$log),
        ], $status);
    }
}
