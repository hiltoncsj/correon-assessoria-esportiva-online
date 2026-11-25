<?php
declare(strict_types=1);
namespace Correon;

/**
 * Modelos SEMANAIS de treino do treinador ("Treino 1" … "Treino 4", por nível do atleta) e aplicação em atletas.
 * Um modelo = várias sessões (cada uma em um dia da semana) com linhas (etapas/exercícios).
 * Aplicar cria uma CÓPIA independente por sessão em `workouts` (editável individualmente).
 * Modelos são privados do treinador que os criou (outro treinador recebe 404).
 */
final class Templates
{
    public const WEEKDAYS = [1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado', 7 => 'Domingo'];
    public const MAX_SESSIONS = 14;

    /** @return list<string> modalidades em que o treinador tem ao menos um atleta ativo */
    public static function modalities(int $trainerId): array
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

    /** @return list<array<string,mixed>> sessões com suas linhas */
    private static function sessions(int $templateId): array
    {
        $out = [];
        foreach (Db::all('SELECT id, weekday, title, discipline, notes FROM workout_template_sessions WHERE template_id = ? ORDER BY position', [$templateId]) as $s) {
            $s['items'] = Db::all(
                'SELECT label, sets, reps, load_text, rest_seconds, duration_minutes, distance_km, target, hr_zone, notes
                 FROM workout_template_items WHERE session_id = ? ORDER BY position',
                [$s['id']]
            );
            $out[] = $s;
        }
        return $out;
    }

    // ---- biblioteca ----

    public static function index(): never
    {
        $u = App::require('trainer');
        $rows = Db::all(
            "SELECT t.id, t.name, t.level_description, t.archived_at, m.name AS modality,
               (SELECT COUNT(*) FROM workout_template_sessions s WHERE s.template_id = t.id) AS session_count,
               (SELECT COUNT(*) FROM trainer_athletes ta WHERE ta.default_template_id = t.id AND ta.status = 'active') AS athletes
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
        self::form($mod, null, ['name' => '', 'level_description' => ''], [], [], 200);
    }

    public static function create(): never
    {
        $u = App::require('trainer');
        $mod = Http::input('modality');
        if (!in_array($mod, self::modalities((int) $u['id']), true)) {
            App::notFound();
        }
        [$head, $sessions, $errors] = self::read($mod);
        if ($errors) {
            self::form($mod, null, $head, self::rawSessions(), $errors, 422);
        }
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            Db::run('INSERT INTO workout_templates (trainer_id, modality_id, discipline, name, level_description) VALUES (?,?,?,?,?)',
                [$u['id'], Workout::modalityId($mod), $sessions[0]['discipline'], $head['name'], $head['level_description'] === '' ? null : $head['level_description']]);
            $id = (int) $pdo->lastInsertId();
            self::saveSessions($id, $sessions);
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
        self::form($t['modality'], $t, ['name' => $t['name'], 'level_description' => (string) $t['level_description']], self::sessions($id), [], 200);
    }

    public static function update(int $id): never
    {
        $u = App::require('trainer');
        $t = self::own($u, $id);
        $action = Http::input('action');
        if ($action === 'archive' || $action === 'unarchive') {
            Db::run('UPDATE workout_templates SET archived_at = ' . ($action === 'archive' ? 'NOW()' : 'NULL') . ' WHERE id = ?', [$id]);
            if ($action === 'archive') { // atletas deixam de ter este modelo como padrão
                Db::run('UPDATE trainer_athletes SET default_template_id = NULL WHERE default_template_id = ?', [$id]);
            }
            Audit::log('template_' . $action . 'd', (int) $u['id'], 'template', $id);
            Http::flash('ok', $action === 'archive' ? 'Modelo arquivado. Os treinos já aplicados continuam como estão.' : 'Modelo reativado.');
            Http::redirect('/treinador/modelos/' . $id);
        }
        if ($action !== 'save') {
            App::notFound();
        }
        [$head, $sessions, $errors] = self::read($t['modality']);
        if ($errors) {
            self::form($t['modality'], $t, $head, self::rawSessions(), $errors, 422);
        }
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            Db::run('UPDATE workout_templates SET name = ?, level_description = ? WHERE id = ?',
                [$head['name'], $head['level_description'] === '' ? null : $head['level_description'], $id]);
            self::saveSessions($id, $sessions);
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
        $templates = $athletes = [];
        if ($mod !== '') {
            $templates = Db::all(
                "SELECT t.id, t.name, t.level_description
                 FROM workout_templates t JOIN modalities m ON m.id = t.modality_id
                 WHERE t.trainer_id = ? AND m.slug = ? AND t.archived_at IS NULL
                   AND EXISTS (SELECT 1 FROM workout_template_items i WHERE i.template_id = t.id) ORDER BY t.name",
                [$u['id'], $mod]
            );
            $athletes = Db::all(
                "SELECT a.id, a.name, ta.default_template_id,
                   (SELECT COUNT(*) FROM athlete_hr_zones z WHERE z.athlete_id = a.id) AS zone_count
                 FROM trainer_athletes ta JOIN users a ON a.id = ta.athlete_id JOIN modalities m ON m.id = ta.modality_id
                 WHERE ta.trainer_id = ? AND m.slug = ? AND ta.status = 'active' ORDER BY a.name",
                [$u['id'], $mod]
            );
        }
        View::render('trainer/apply', ['title' => 'Aplicar modelos', 'shell' => 'trainer', 'active' => 'aplicar',
            'mods' => $mods, 'mod' => $mod, 'templates' => $templates, 'athletes' => $athletes,
            'monday' => self::monday(date('Y-m-d'))]);
    }

    public static function applySave(): never
    {
        $u = App::require('trainer');
        $t = (int) $u['id'];
        $mod = Http::input('modality');
        if (!in_array($mod, self::modalities($t), true)) {
            App::notFound();
        }
        $back = '/treinador/modelos/aplicar?modalidade=' . $mod;
        $modId = Workout::modalityId($mod);
        $date = Http::input('scheduled_date');
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date || $d < new \DateTimeImmutable('-1 year') || $d > new \DateTimeImmutable('+2 years')) {
            Http::flash('err', 'Informe uma data válida para a semana.');
            Http::redirect($back);
        }
        $monday = self::monday($date);
        $status = Http::input('publish') === '1' ? 'published' : 'draft';

        $picks = [];
        foreach (is_array($_POST['pick'] ?? null) ? $_POST['pick'] : [] as $athleteId => $tplId) {
            if (is_string($tplId) && $tplId !== '' && ctype_digit((string) $athleteId) && ctype_digit($tplId)) {
                $picks[(int) $athleteId] = (int) $tplId;
            }
        }
        // Formato agrupado por treino: tpl[ID_DO_TREINO][] = ID_DO_ATLETA (um atleta não pode estar em dois treinos)
        foreach (is_array($_POST['tpl'] ?? null) ? $_POST['tpl'] : [] as $tplId => $athleteIds) {
            if (!ctype_digit((string) $tplId) || !is_array($athleteIds)) {
                continue;
            }
            foreach ($athleteIds as $aid) {
                if (!is_string($aid) || !ctype_digit($aid)) {
                    continue;
                }
                if (isset($picks[(int) $aid]) && $picks[(int) $aid] !== (int) $tplId) {
                    Http::flash('err', 'O mesmo atleta foi marcado em mais de um treino. Nada foi criado.');
                    Http::redirect($back);
                }
                $picks[(int) $aid] = (int) $tplId;
            }
        }
        if (!$picks) {
            Http::flash('err', 'Marque pelo menos um atleta em algum treino.');
            Http::redirect($back);
        }
        // Validação no servidor: atleta vinculado nesta modalidade; modelo próprio, ativo, da modalidade e com sessões.
        $tpl = [];
        foreach ($picks as $athleteId => $tplId) {
            if (!in_array($mod, Workout::linkedModalities($t, $athleteId), true)) {
                Http::flash('err', 'Seleção inválida: atleta não vinculado a você nesta modalidade. Nada foi criado.');
                Http::redirect($back);
            }
            if (!isset($tpl[$tplId])) {
                $row = Db::one('SELECT id, name FROM workout_templates WHERE id = ? AND trainer_id = ? AND modality_id = ? AND archived_at IS NULL', [$tplId, $t, $modId]);
                $tpl[$tplId] = $row ? $row + ['sessions' => self::sessions($tplId)] : false;
            }
            if (!$tpl[$tplId] || !$tpl[$tplId]['sessions']) {
                Http::flash('err', 'Seleção inválida: modelo inexistente, arquivado ou vazio. Nada foi criado.');
                Http::redirect($back);
            }
        }

        $created = 0;
        $skipped = [];
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($picks as $athleteId => $tplId) {
                foreach ($tpl[$tplId]['sessions'] as $s) {
                    $day = (new \DateTimeImmutable($monday))->modify('+' . ((int) $s['weekday'] - 1) . ' days')->format('Y-m-d');
                    $title = mb_substr($tpl[$tplId]['name'] . ' · ' . $s['title'], 0, 120);
                    $dup = Db::one("SELECT id FROM workouts WHERE athlete_id = ? AND scheduled_date = ? AND source_template_id = ? AND title = ? AND status <> 'cancelled'",
                        [$athleteId, $day, $tplId, $title]);
                    if ($dup) {
                        $skipped[$athleteId] = (string) Db::one('SELECT name FROM users WHERE id = ?', [$athleteId])['name'];
                        continue;
                    }
                    Db::run(
                        'INSERT INTO workouts (athlete_id, modality_id, discipline, scheduled_date, title, notes, status, published_at, created_by, updated_by, source_template_id)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                        [$athleteId, $modId, $s['discipline'], $day, $title, $s['notes'], $status,
                            $status === 'published' ? date('Y-m-d H:i:s') : null, $t, $t, $tplId]
                    );
                    $wid = (int) $pdo->lastInsertId();
                    Db::run(
                        'INSERT INTO workout_items (workout_id, position, label, sets, reps, load_text, rest_seconds, duration_minutes, distance_km, target, hr_zone, notes)
                         SELECT ?, position, label, sets, reps, load_text, rest_seconds, duration_minutes, distance_km, target, hr_zone, notes
                         FROM workout_template_items WHERE session_id = ? ORDER BY position',
                        [$wid, $s['id']]
                    );
                    Workout::snapshot($wid, $status === 'published' ? 'published' : 'created', $t);
                    Audit::log('workout_from_template', $t, 'workout', $wid, ['athlete_id' => $athleteId, 'template_id' => $tplId]);
                    $created++;
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        $msg = $created . ' treino(s) ' . ($status === 'published' ? 'publicado(s)' : 'salvo(s) como rascunho') . ' na semana de ' . date('d/m/Y', strtotime($monday)) . '.';
        if ($skipped) {
            $msg .= ' Já existiam e foram ignorados: ' . implode(', ', $skipped) . '.';
        }
        Http::flash($created ? 'ok' : 'err', $msg);
        Http::redirect($back);
    }

    /** Segunda-feira da semana (ISO) que contém a data. */
    public static function monday(string $ymd): string
    {
        return (new \DateTimeImmutable($ymd))->modify('monday this week')->format('Y-m-d');
    }

    // ---- leitura do formulário ----

    /**
     * @return array{0:array<string,string>,1:list<array<string,mixed>>,2:array<string,string>} [cabeçalho, sessões válidas, erros]
     */
    private static function read(string $modality): array
    {
        $errors = [];
        $name = trim(preg_replace('/\s+/u', ' ', Http::input('name')) ?? '');
        $level = trim(Http::input('level_description'));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $errors['name'] = 'Informe o nome do modelo (2 a 120 caracteres).';
        }
        if (mb_strlen($level) > 120) {
            $errors['level_description'] = 'Máximo de 120 caracteres.';
        }

        $allowed = Workout::MODALITY_DISCIPLINES[$modality];
        $sessions = [];
        $msgs = [];
        $n = 0;
        foreach (is_array($_POST['sessions'] ?? null) ? $_POST['sessions'] : [] as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $f = static fn (string $k): string => is_string($raw[$k] ?? null) ? trim($raw[$k]) : '';
            $title = trim(preg_replace('/\s+/u', ' ', $f('title')) ?? '');
            $notes = $f('notes');
            $discipline = count($allowed) === 1 ? $allowed[0] : $f('discipline');
            [$items, $itemErrors] = Workout::parseItems($raw['items'] ?? null, in_array($discipline, $allowed, true) ? $discipline : $allowed[0]);
            if ($title === '' && $notes === '' && !$items && !$itemErrors) {
                continue; // sessão em branco
            }
            $n++;
            $tag = "Sessão $n: ";
            if ($n > self::MAX_SESSIONS) {
                $msgs[] = 'No máximo ' . self::MAX_SESSIONS . ' sessões por modelo.';
                break;
            }
            $weekday = ctype_digit($f('weekday')) ? (int) $f('weekday') : 0;
            if ($weekday < 1 || $weekday > 7) {
                $msgs[] = $tag . 'escolha o dia da semana.';
            }
            if (mb_strlen($title) < 2 || mb_strlen($title) > 120) {
                $msgs[] = $tag . 'informe o título (2 a 120 caracteres).';
            }
            if (!in_array($discipline, $allowed, true)) {
                $msgs[] = $tag . 'disciplina inválida para esta modalidade.';
            }
            if (mb_strlen($notes) > 1000) {
                $msgs[] = $tag . 'orientações com mais de 1000 caracteres.';
            }
            foreach ($itemErrors as $ie) {
                $msgs[] = $tag . $ie;
            }
            if (!$items && !$itemErrors) {
                $msgs[] = $tag . 'adicione pelo menos uma linha.';
            }
            $sessions[] = ['weekday' => $weekday, 'title' => $title, 'discipline' => $discipline, 'notes' => $notes, 'items' => $items];
        }
        if (!$sessions && !$msgs) {
            $msgs[] = 'Adicione pelo menos uma sessão (um dia de treino) ao modelo.';
        }
        if ($msgs) {
            $errors['sessions'] = implode(' ', $msgs);
        }
        return [['name' => $name, 'level_description' => $level], $sessions, $errors];
    }

    /** Devolve o que foi digitado, para reexibir o formulário com erro. @return list<array<string,mixed>> */
    private static function rawSessions(): array
    {
        $out = [];
        foreach (is_array($_POST['sessions'] ?? null) ? $_POST['sessions'] : [] as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $s = [];
            foreach (['weekday', 'title', 'discipline', 'notes'] as $k) {
                $s[$k] = is_string($raw[$k] ?? null) ? $raw[$k] : '';
            }
            $s['items'] = [];
            foreach (is_array($raw['items'] ?? null) ? $raw['items'] : [] as $row) {
                if (is_array($row)) {
                    $s['items'][] = array_map(static fn ($v) => is_string($v) ? $v : '', $row);
                }
            }
            $out[] = $s;
        }
        return $out;
    }

    /** @param list<array<string,mixed>> $sessions */
    private static function saveSessions(int $templateId, array $sessions): void
    {
        Db::run('DELETE FROM workout_template_items WHERE template_id = ?', [$templateId]);
        Db::run('DELETE FROM workout_template_sessions WHERE template_id = ?', [$templateId]);
        foreach ($sessions as $si => $s) {
            Db::run('INSERT INTO workout_template_sessions (template_id, weekday, position, title, discipline, notes) VALUES (?,?,?,?,?,?)',
                [$templateId, $s['weekday'], $si + 1, $s['title'], $s['discipline'], $s['notes'] === '' ? null : $s['notes']]);
            $sid = (int) Db::pdo()->lastInsertId();
            foreach ($s['items'] as $i => $it) {
                Db::run(
                    'INSERT INTO workout_template_items (template_id, session_id, position, label, sets, reps, load_text, rest_seconds, duration_minutes, distance_km, target, hr_zone, notes)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [$templateId, $sid, $i + 1, $it['label'], $it['sets'], $it['reps'], $it['load_text'], $it['rest_seconds'],
                        $it['duration_minutes'], $it['distance_km'], $it['target'], $it['hr_zone'], $it['notes']]
                );
            }
        }
    }

    /**
     * @param array<string,mixed>|null $t
     * @param array<string,string> $vals
     * @param list<array<string,mixed>> $sessions
     * @param array<string,string> $errors
     */
    private static function form(string $modality, ?array $t, array $vals, array $sessions, array $errors, int $status): never
    {
        View::render('trainer/template_form', [
            'title' => $t ? $t['name'] : 'Novo modelo', 'shell' => 'trainer', 'active' => 'modelos',
            'modality' => $modality, 'modalityName' => Db::one('SELECT name FROM modalities WHERE slug = ?', [$modality])['name'] ?? '',
            't' => $t, 'vals' => $vals, 'sessions' => $sessions, 'errors' => $errors,
            'disciplines' => Workout::MODALITY_DISCIPLINES[$modality], 'isNew' => $t === null,
        ], $status);
    }
}
