<?php
declare(strict_types=1);
namespace Correon;

/**
 * Modelos de treino do treinador ("Treino 1", "Treino 2"...) e aplicação em vários atletas.
 * Cada aplicação cria uma CÓPIA independente em `workouts` (editável individualmente).
 * Modelos são privados do treinador que os criou (outro treinador recebe 404).
 */
final class Templates
{
    /** @return list<string> modalidades em que o treinador tem ao menos um atleta ativo */
    private static function modalities(int $trainerId): array
    {
        return array_column(Db::all(
            "SELECT m.slug FROM trainer_athletes ta JOIN modalities m ON m.id = ta.modality_id
             WHERE ta.trainer_id = ? AND ta.status = 'active' GROUP BY m.id, m.slug ORDER BY m.id",
            [$trainerId]
        ), 'slug');
    }

    /** Modelo do próprio treinador; senão 404. @return array<string,mixed> */
    private static function own(array $u, int $id): array
    {
        $t = Db::one('SELECT t.*, m.slug AS modality, m.name AS modality_name FROM workout_templates t JOIN modalities m ON m.id = t.modality_id
                      WHERE t.id = ? AND t.trainer_id = ?', [$id, $u['id']]);
        if (!$t) {
            App::notFound();
        }
        return $t;
    }

    /** @return list<array<string,mixed>> */
    private static function items(int $templateId): array
    {
        return Db::all('SELECT label, sets, reps, load_text, rest_seconds, duration_minutes, distance_km, target, notes
                        FROM workout_template_items WHERE template_id = ? ORDER BY position', [$templateId]);
    }

    // ---- biblioteca ----

    public static function index(): never
    {
        $u = App::require('trainer');
        $rows = Db::all(
            "SELECT t.id, t.name, t.discipline, t.archived_at, m.name AS modality,
               (SELECT COUNT(*) FROM workout_template_items i WHERE i.template_id = t.id) AS line_count,
               (SELECT COUNT(*) FROM workouts w WHERE w.source_template_id = t.id) AS used
             FROM workout_templates t JOIN modalities m ON m.id = t.modality_id
             WHERE t.trainer_id = ? ORDER BY t.archived_at IS NOT NULL, m.id, t.name",
            [$u['id']]
        );
        View::render('trainer/templates', ['title' => 'Modelos de treino', 'shell' => 'trainer', 'active' => 'modelos',
            'rows' => $rows, 'mods' => self::modalities((int) $u['id'])]);
    }

    public static function newForm(): never
    {
        $u = App::require('trainer');
        $mod = (string) ($_GET['modalidade'] ?? '');
        if (!in_array($mod, self::modalities((int) $u['id']), true)) {
            App::notFound();
        }
        self::form($mod, null, ['name' => '', 'notes' => '', 'discipline' => ''], [], [], 200);
    }

    public static function create(): never
    {
        $u = App::require('trainer');
        $mod = Http::input('modality');
        if (!in_array($mod, self::modalities((int) $u['id']), true)) {
            App::notFound();
        }
        [$data, $errors, $items] = self::read($mod, null);
        if ($errors) {
            self::form($mod, null, $data, self::rawItems(), $errors, 422);
        }
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            Db::run('INSERT INTO workout_templates (trainer_id, modality_id, discipline, name, notes) VALUES (?,?,?,?,?)',
                [$u['id'], Workout::modalityId($mod), $data['discipline'], $data['name'], $data['notes'] === '' ? null : $data['notes']]);
            $id = (int) $pdo->lastInsertId();
            self::replaceItems($id, $items);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        Audit::log('template_created', (int) $u['id'], 'template', $id);
        Http::flash('ok', 'Modelo salvo.');
        Http::redirect('/treinador/modelos/' . $id);
    }

    public static function edit(int $id): never
    {
        $u = App::require('trainer');
        $t = self::own($u, $id);
        self::form($t['modality'], $t, ['name' => $t['name'], 'notes' => (string) $t['notes'], 'discipline' => $t['discipline']],
            self::items($id), [], 200);
    }

    public static function update(int $id): never
    {
        $u = App::require('trainer');
        $t = self::own($u, $id);
        $action = Http::input('action');
        if ($action === 'archive' || $action === 'unarchive') {
            Db::run('UPDATE workout_templates SET archived_at = ' . ($action === 'archive' ? 'NOW()' : 'NULL') . ' WHERE id = ?', [$id]);
            Audit::log('template_' . $action . 'd', (int) $u['id'], 'template', $id);
            Http::flash('ok', $action === 'archive' ? 'Modelo arquivado. Os treinos já aplicados continuam como estão.' : 'Modelo reativado.');
            Http::redirect('/treinador/modelos/' . $id);
        }
        if ($action !== 'save') {
            App::notFound();
        }
        [$data, $errors, $items] = self::read($t['modality'], $t['discipline']);
        if ($errors) {
            self::form($t['modality'], $t, $data, self::rawItems(), $errors, 422);
        }
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            Db::run('UPDATE workout_templates SET name = ?, notes = ? WHERE id = ?', [$data['name'], $data['notes'] === '' ? null : $data['notes'], $id]);
            self::replaceItems($id, $items);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        Audit::log('template_updated', (int) $u['id'], 'template', $id);
        Http::flash('ok', 'Modelo salvo. Treinos já aplicados a atletas não foram alterados.');
        Http::redirect('/treinador/modelos/' . $id);
    }

    // ---- aplicar a atletas ----

    public static function applyForm(): never
    {
        $u = App::require('trainer');
        $mods = self::modalities((int) $u['id']);
        $mod = (string) ($_GET['modalidade'] ?? ($mods[0] ?? ''));
        if ($mods && !in_array($mod, $mods, true)) {
            App::notFound();
        }
        self::applyView($u, $mods, $mod, 200);
    }

    public static function applySave(): never
    {
        $u = App::require('trainer');
        $t = (int) $u['id'];
        $mod = Http::input('modality');
        if (!in_array($mod, self::modalities($t), true)) {
            App::notFound();
        }
        $modId = Workout::modalityId($mod);
        $date = Http::input('scheduled_date');
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date || $d < new \DateTimeImmutable('-1 year') || $d > new \DateTimeImmutable('+2 years')) {
            Http::flash('err', 'Informe uma data válida para o treino.');
            Http::redirect('/treinador/modelos/aplicar?modalidade=' . $mod);
        }
        $status = Http::input('publish') === '1' ? 'published' : 'draft';

        $picks = [];
        foreach (is_array($_POST['pick'] ?? null) ? $_POST['pick'] : [] as $athleteId => $tplId) {
            if (is_string($tplId) && $tplId !== '' && ctype_digit((string) $athleteId) && ctype_digit($tplId)) {
                $picks[(int) $athleteId] = (int) $tplId;
            }
        }
        if (!$picks) {
            Http::flash('err', 'Escolha um treino para pelo menos um atleta.');
            Http::redirect('/treinador/modelos/aplicar?modalidade=' . $mod);
        }
        // Validação no servidor: atleta vinculado nesta modalidade e modelo próprio, ativo, da modalidade e com linhas.
        $tpl = [];
        foreach ($picks as $athleteId => $tplId) {
            if (!in_array($mod, Workout::linkedModalities($t, $athleteId), true)) {
                Http::flash('err', 'Seleção inválida: atleta não vinculado a você nesta modalidade. Nada foi criado.');
                Http::redirect('/treinador/modelos/aplicar?modalidade=' . $mod);
            }
            $tpl[$tplId] ??= Db::one(
                'SELECT t.id, t.name, t.discipline, t.notes, (SELECT COUNT(*) FROM workout_template_items i WHERE i.template_id = t.id) AS line_count
                 FROM workout_templates t WHERE t.id = ? AND t.trainer_id = ? AND t.modality_id = ? AND t.archived_at IS NULL',
                [$tplId, $t, $modId]
            );
            if (!$tpl[$tplId] || (int) $tpl[$tplId]['line_count'] < 1) {
                Http::flash('err', 'Seleção inválida: modelo inexistente, arquivado ou vazio. Nada foi criado.');
                Http::redirect('/treinador/modelos/aplicar?modalidade=' . $mod);
            }
        }

        $created = 0;
        $skipped = [];
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($picks as $athleteId => $tplId) {
                $m = $tpl[$tplId];
                $dup = Db::one("SELECT id FROM workouts WHERE athlete_id = ? AND scheduled_date = ? AND source_template_id = ? AND status <> 'cancelled'",
                    [$athleteId, $date, $tplId]);
                if ($dup) {
                    $skipped[] = (string) Db::one('SELECT name FROM users WHERE id = ?', [$athleteId])['name'];
                    continue;
                }
                Db::run(
                    'INSERT INTO workouts (athlete_id, modality_id, discipline, scheduled_date, title, notes, status, published_at, created_by, updated_by, source_template_id)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                    [$athleteId, $modId, $m['discipline'], $date, $m['name'], $m['notes'], $status,
                        $status === 'published' ? date('Y-m-d H:i:s') : null, $t, $t, $tplId]
                );
                $wid = (int) $pdo->lastInsertId();
                Db::run(
                    'INSERT INTO workout_items (workout_id, position, label, sets, reps, load_text, rest_seconds, duration_minutes, distance_km, target, notes)
                     SELECT ?, position, label, sets, reps, load_text, rest_seconds, duration_minutes, distance_km, target, notes
                     FROM workout_template_items WHERE template_id = ? ORDER BY position',
                    [$wid, $tplId]
                );
                Workout::snapshot($wid, $status === 'published' ? 'published' : 'created', $t);
                Audit::log('workout_from_template', $t, 'workout', $wid, ['athlete_id' => $athleteId, 'template_id' => $tplId]);
                $created++;
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        $msg = $created . ' treino(s) ' . ($status === 'published' ? 'publicado(s)' : 'salvo(s) como rascunho') . ' para ' . date('d/m/Y', strtotime($date)) . '.';
        if ($skipped) {
            $msg .= ' Já existiam e foram ignorados: ' . implode(', ', $skipped) . '.';
        }
        Http::flash($created ? 'ok' : 'err', $msg);
        Http::redirect('/treinador/modelos/aplicar?modalidade=' . $mod);
    }

    // ---- auxiliares ----

    /** @param list<string> $mods */
    private static function applyView(array $u, array $mods, string $mod, int $status): never
    {
        $templates = $athletes = [];
        if ($mod !== '') {
            $templates = Db::all(
                "SELECT t.id, t.name, t.discipline, (SELECT COUNT(*) FROM workout_template_items i WHERE i.template_id = t.id) AS line_count
                 FROM workout_templates t JOIN modalities m ON m.id = t.modality_id
                 WHERE t.trainer_id = ? AND m.slug = ? AND t.archived_at IS NULL ORDER BY t.name",
                [$u['id'], $mod]
            );
            $athletes = Db::all(
                "SELECT a.id, a.name FROM trainer_athletes ta JOIN users a ON a.id = ta.athlete_id JOIN modalities m ON m.id = ta.modality_id
                 WHERE ta.trainer_id = ? AND m.slug = ? AND ta.status = 'active' ORDER BY a.name",
                [$u['id'], $mod]
            );
        }
        View::render('trainer/apply', ['title' => 'Aplicar modelos', 'shell' => 'trainer', 'active' => 'aplicar',
            'mods' => $mods, 'mod' => $mod, 'templates' => array_values(array_filter($templates, static fn ($x) => (int) $x['line_count'] > 0)),
            'athletes' => $athletes], $status);
    }

    /** @return array{0:array<string,string>,1:array<string,string>,2:list<array<string,mixed>>} */
    private static function read(string $modality, ?string $fixedDiscipline): array
    {
        $in = ['title' => Http::input('name'), 'scheduled_date' => date('Y-m-d'), 'notes' => Http::input('notes'), 'discipline' => Http::input('discipline')];
        $allowed = Workout::MODALITY_DISCIPLINES[$modality];
        if ($fixedDiscipline === null && count($allowed) === 1) {
            $fixedDiscipline = $allowed[0];
        }
        [$data, $errors] = Workout::validateHeader($in, $modality, $fixedDiscipline);
        unset($errors['scheduled_date']);
        if (isset($errors['title'])) {
            $errors['name'] = 'Informe o nome do modelo (2 a 120 caracteres).';
            unset($errors['title']);
        }
        [$items, $itemErrors] = Workout::parseItems($_POST['items'] ?? null, $data['discipline']);
        if ($itemErrors) {
            $errors['items'] = implode(' ', $itemErrors);
        } elseif (!$items) {
            $errors['items'] = 'Adicione pelo menos uma linha ao modelo.';
        }
        return [['name' => $data['title'], 'notes' => $data['notes'], 'discipline' => $data['discipline']], $errors, $items];
    }

    /** @return list<array<string,mixed>> */
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

    /** @param list<array<string,mixed>> $items */
    private static function replaceItems(int $templateId, array $items): void
    {
        Db::run('DELETE FROM workout_template_items WHERE template_id = ?', [$templateId]);
        foreach ($items as $i => $it) {
            Db::run(
                'INSERT INTO workout_template_items (template_id, position, label, sets, reps, load_text, rest_seconds, duration_minutes, distance_km, target, notes)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [$templateId, $i + 1, $it['label'], $it['sets'], $it['reps'], $it['load_text'], $it['rest_seconds'],
                    $it['duration_minutes'], $it['distance_km'], $it['target'], $it['notes']]
            );
        }
    }

    /**
     * @param array<string,mixed>|null $t
     * @param array<string,string> $vals
     * @param list<array<string,mixed>> $items
     * @param array<string,string> $errors
     */
    private static function form(string $modality, ?array $t, array $vals, array $items, array $errors, int $status): never
    {
        $disciplines = Workout::MODALITY_DISCIPLINES[$modality];
        $discipline = $vals['discipline'] !== '' ? $vals['discipline'] : $disciplines[0];
        View::render('trainer/template_form', [
            'title' => $t ? $t['name'] : 'Novo modelo', 'shell' => 'trainer', 'active' => 'modelos',
            'modality' => $modality, 'modalityName' => Db::one('SELECT name FROM modalities WHERE slug = ?', [$modality])['name'] ?? '',
            't' => $t, 'vals' => $vals, 'items' => $items, 'errors' => $errors,
            'disciplines' => $disciplines, 'discipline' => $discipline, 'isNew' => $t === null,
        ], $status);
    }
}
