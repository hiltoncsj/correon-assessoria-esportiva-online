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
        $levels = Db::all(
            "SELECT m.slug, m.name, ta.default_template_id, ta.train_days FROM trainer_athletes ta JOIN modalities m ON m.id = ta.modality_id
             WHERE ta.trainer_id = ? AND ta.athlete_id = ? AND ta.status = 'active' ORDER BY m.id",
            [$u['id'], $athleteId]
        );
        foreach ($levels as &$lv) {
            $lv['templates'] = Db::all(
                'SELECT t.id, t.name, t.level_description FROM workout_templates t JOIN modalities m ON m.id = t.modality_id
                 WHERE t.trainer_id = ? AND m.slug = ? AND t.archived_at IS NULL ORDER BY t.name',
                [$u['id'], $lv['slug']]
            );
        }
        unset($lv);
        View::render('trainer/athlete', [
            'title' => $athlete['name'], 'shell' => 'trainer', 'active' => 'home',
            'athlete' => $athlete, 'mods' => $mods, 'workouts' => $workouts,
            'zones' => Workout::zones($athleteId), 'maxHr' => Workout::maxHr($athleteId), 'levels' => $levels,
            'profile' => self::athleteProfile((int) $u['id'], $athleteId),
            'summary' => Db::one(
                "SELECT COUNT(*) AS planned, SUM(l.outcome IN ('completed','partial')) AS done,
                        SUM(l.id IS NULL AND w.scheduled_date < CURDATE()) AS missing, ROUND(AVG(l.effort), 1) AS effort
                 FROM workouts w JOIN modalities m ON m.id = w.modality_id LEFT JOIN workout_logs l ON l.workout_id = w.id
                 WHERE w.athlete_id = ? AND m.slug IN ($in) AND w.status = 'published'
                   AND w.scheduled_date BETWEEN (CURDATE() - INTERVAL 28 DAY) AND CURDATE()",
                [$athleteId, ...$mods]
            ),
        ]);
    }

    /**
     * Dados do perfil que o treinador vinculado precisa (idade em vez da data de nascimento).
     * O acesso é auditado; o atleta é informado desta visibilidade na tela "Meu perfil".
     * @return array<string,mixed>
     */
    private static function athleteProfile(int $trainerId, int $athleteId): array
    {
        $p = Db::one('SELECT phone, birth_date, city, state, goal, sports_background, emergency_name, emergency_phone
                      FROM athlete_profiles WHERE user_id = ?', [$athleteId]) ?? [];
        if (!empty($p['birth_date'])) {
            $p['age'] = (new \DateTimeImmutable($p['birth_date']))->diff(new \DateTimeImmutable('today'))->y;
        }
        unset($p['birth_date']);
        Audit::log('athlete_profile_viewed', $trainerId, 'user', $athleteId);
        return $p;
    }

    /**
     * Zonas de FC do atleta (treinador com vínculo ativo).
     * action=defaults: calcula as 7 zonas pelos percentuais padrão a partir da FC máxima.
     * action=save: grava as faixas digitadas (ajuste individual) e a FC máxima, se informada.
     */
    public static function zonesSave(int $athleteId): never
    {
        $u = App::require('trainer');
        if (!Workout::linkedModalities((int) $u['id'], $athleteId)) {
            App::notFound();
        }
        $back = '/treinador/atletas/' . $athleteId . '#zonas';
        $action = Http::input('action');
        $maxRaw = Http::input('max_hr');
        $max = null;
        if ($maxRaw !== '') {
            if (!ctype_digit($maxRaw) || (int) $maxRaw < 100 || (int) $maxRaw > 230) {
                Http::flash('err', 'FC máxima inválida (entre 100 e 230 bpm).');
                Http::redirect($back);
            }
            $max = (int) $maxRaw;
        }
        if ($action === 'defaults') {
            if ($max === null) {
                Http::flash('err', 'Informe a FC máxima do atleta para calcular as zonas.');
                Http::redirect($back);
            }
            $zones = Workout::computeZones($max);
        } elseif ($action === 'save') {
            [$zones, $err] = Workout::parseZones($_POST['zones'] ?? null);
            if ($err !== null) {
                Http::flash('err', $err);
                Http::redirect($back);
            }
        } else {
            App::notFound();
        }
        self::storeZones($athleteId, $zones, $max, (int) $u['id']);
        Audit::log('hr_zones_updated', (int) $u['id'], 'user', $athleteId, ['zones' => count($zones), 'mode' => $action]); // sem os valores (dado pessoal)
        Http::flash('ok', $action === 'defaults' ? 'Zonas calculadas pela FC máxima. Ajuste individualmente o que precisar.' : 'Zonas de frequência cardíaca salvas.');
        Http::redirect($back);
    }

    /** Todos os atletas do treinador com as 7 zonas de FC, para ajuste individual. */
    public static function zonesAll(): never
    {
        $u = App::require('trainer');
        self::zonesTable($u, null, [], 200);
    }

    /**
     * z[ID][max] e z[ID][zones][n][min|max]; calc=ID calcula pelo padrão só daquele atleta.
     * Grava apenas as linhas que mudaram; se alguma linha for inválida, nada é gravado.
     */
    public static function zonesAllSave(): never
    {
        $u = App::require('trainer');
        $t = (int) $u['id'];
        if (!Http::formComplete()) {
            Http::flash('err', 'A tabela ficou grande demais para o servidor e chegou incompleta. Nada foi salvo. Ajuste pela página de cada atleta ou avise o suporte.');
            Http::redirect('/treinador/zonas');
        }
        $calc = Http::input('calc');
        $rows = is_array($_POST['z'] ?? null) ? $_POST['z'] : [];
        $changes = [];
        $errors = [];
        foreach ($rows as $athleteId => $row) {
            if (!ctype_digit((string) $athleteId) || !is_array($row) || !Workout::linkedModalities($t, (int) $athleteId)) {
                App::notFound();
            }
            $athleteId = (int) $athleteId;
            $maxRaw = is_string($row['max'] ?? null) ? trim($row['max']) : '';
            $max = null;
            if ($maxRaw !== '') {
                if (!ctype_digit($maxRaw) || (int) $maxRaw < 100 || (int) $maxRaw > 230) {
                    $errors[$athleteId] = 'FC máxima inválida (100 a 230).';
                    continue;
                }
                $max = (int) $maxRaw;
            }
            if ($calc === (string) $athleteId) {
                if ($max === null) {
                    $errors[$athleteId] = 'Informe a FC máxima para calcular.';
                    continue;
                }
                $zones = Workout::computeZones($max);
            } else {
                [$zones, $err] = Workout::parseZones($row['zones'] ?? null);
                if ($err !== null) {
                    $errors[$athleteId] = $err;
                    continue;
                }
            }
            if ($zones != Workout::zones($athleteId) || ($max !== null && $max !== Workout::maxHr($athleteId))) {
                $changes[$athleteId] = [$zones, $max];
            }
        }
        if ($errors) {
            self::zonesTable($u, $rows, $errors, 422);
        }
        foreach ($changes as $athleteId => [$zones, $max]) {
            self::storeZones($athleteId, $zones, $max, $t);
            Audit::log('hr_zones_updated', $t, 'user', $athleteId, ['zones' => count($zones), 'mode' => $calc === (string) $athleteId ? 'defaults' : 'save']);
        }
        Http::flash('ok', $changes ? count($changes) . ' atleta(s) com zonas atualizadas.' : 'Nenhuma alteração.');
        Http::redirect('/treinador/zonas');
    }

    /** @param array<int|string,mixed>|null $posted valores digitados (para reexibir após erro) @param array<int,string> $errors */
    private static function zonesTable(array $u, ?array $posted, array $errors, int $status): never
    {
        $athletes = Db::all(
            "SELECT a.id, a.name, GROUP_CONCAT(m.name ORDER BY m.id SEPARATOR ', ') AS modalities
             FROM trainer_athletes ta JOIN users a ON a.id = ta.athlete_id JOIN modalities m ON m.id = ta.modality_id
             WHERE ta.trainer_id = ? AND ta.status = 'active' GROUP BY a.id, a.name ORDER BY a.name",
            [$u['id']]
        );
        foreach ($athletes as &$a) {
            $id = (int) $a['id'];
            $p = $posted[$id] ?? null;
            $zones = Workout::zones($id);
            $a['max'] = $p !== null ? (string) (is_string($p['max'] ?? null) ? $p['max'] : '') : (string) (Workout::maxHr($id) ?? '');
            for ($z = 1; $z <= Workout::ZONES; $z++) {
                foreach (['min', 'max'] as $k) {
                    $a['zones'][$z][$k] = $p !== null
                        ? (string) (is_string($p['zones'][$z][$k] ?? null) ? $p['zones'][$z][$k] : '')
                        : (string) ($zones[$z][$k] ?? '');
                }
            }
        }
        unset($a);
        View::render('trainer/zones', ['title' => 'Zonas de FC', 'shell' => 'trainer', 'active' => 'zonas',
            'athletes' => $athletes, 'errors' => $errors], $status);
    }

    /** @param array<int,array{min:int,max:int}> $zones */
    private static function storeZones(int $athleteId, array $zones, ?int $max, int $byUser): void
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            Db::run('DELETE FROM athlete_hr_zones WHERE athlete_id = ?', [$athleteId]);
            foreach ($zones as $z => $r) {
                Db::run('INSERT INTO athlete_hr_zones (athlete_id, zone, bpm_min, bpm_max, updated_by) VALUES (?,?,?,?,?)', [$athleteId, $z, $r['min'], $r['max'], $byUser]);
            }
            if ($max !== null) {
                Db::run('INSERT INTO athlete_hr_settings (athlete_id, max_hr, updated_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE max_hr = VALUES(max_hr), updated_by = VALUES(updated_by)',
                    [$athleteId, $max, $byUser]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Define o treino padrão (nível) do atleta numa modalidade. */
    public static function levelSave(int $athleteId): never
    {
        $u = App::require('trainer');
        $mod = Http::input('modality');
        if (!in_array($mod, Workout::linkedModalities((int) $u['id'], $athleteId), true)) {
            App::notFound();
        }
        $tplId = Http::input('template_id');
        $value = null;
        if ($tplId !== '') {
            $row = ctype_digit($tplId) ? Db::one('SELECT id FROM workout_templates WHERE id = ? AND trainer_id = ? AND modality_id = ? AND archived_at IS NULL',
                [(int) $tplId, $u['id'], Workout::modalityId($mod)]) : null;
            if (!$row) {
                App::notFound();
            }
            $value = (int) $row['id'];
        }
        $days = Templates::parseDays(is_array($_POST['days'] ?? null) ? $_POST['days'] : []);
        Db::run('UPDATE trainer_athletes SET default_template_id = ?, train_days = ? WHERE trainer_id = ? AND athlete_id = ? AND modality_id = ? AND status = \'active\'',
            [$value, $days ? implode(',', $days) : null, $u['id'], $athleteId, Workout::modalityId($mod)]);
        Audit::log('athlete_level_set', (int) $u['id'], 'user', $athleteId, ['modality' => $mod, 'template_id' => $value]);
        Http::flash('ok', 'Treino padrão e dias do atleta atualizados.');
        Http::redirect('/treinador/atletas/' . $athleteId . '#nivel');
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
            'zones' => $w ? Workout::zonesForWorkout((int) $w['id'], (int) $athlete['id']) : Workout::zones((int) $athlete['id']),
            'editable' => !$w || ($w['status'] !== 'cancelled' && !$log),
        ], $status);
    }
}
