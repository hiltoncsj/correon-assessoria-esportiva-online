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
    public const MAX_PER_DAY = 10; // teto técnico de treinos no mesmo dia
    public const MAX_SESSIONS = 100; // teto técnico de segurança; não é regra de negócio
    public const DAY_SHORT = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];

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
        foreach (Db::all('SELECT id, title, discipline, notes FROM workout_template_sessions WHERE template_id = ? ORDER BY position', [$templateId]) as $s) {
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
        self::requireComplete('/treinador/modelos/novo?modalidade=' . $mod);
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
        self::requireComplete('/treinador/modelos/' . $id);
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
        $semana = (string) ($_GET['semana'] ?? date('Y-m-d'));
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $semana);
        if (!$d || $d->format('Y-m-d') !== $semana) {
            $semana = date('Y-m-d');
        }
        $monday = self::monday($semana);
        $sunday = (new \DateTimeImmutable($monday))->modify('+6 days')->format('Y-m-d');

        $templates = $pending = $done = [];
        if ($mod !== '') {
            $templates = Db::all(
                "SELECT t.id, t.name, t.level_description
                 FROM workout_templates t JOIN modalities m ON m.id = t.modality_id
                 WHERE t.trainer_id = ? AND m.slug = ? AND t.archived_at IS NULL
                   AND EXISTS (SELECT 1 FROM workout_template_items i WHERE i.template_id = t.id) ORDER BY t.name",
                [$u['id'], $mod]
            );
            // week_status: situação dos treinos do atleta nesta modalidade e semana (NULL = ainda sem treino)
            $athletes = Db::all(
                "SELECT a.id, a.name, ta.default_template_id,
                   (SELECT COUNT(*) FROM athlete_hr_zones z WHERE z.athlete_id = a.id) AS zone_count,
                   (SELECT GROUP_CONCAT(DISTINCT w.status ORDER BY w.status) FROM workouts w
                      WHERE w.athlete_id = a.id AND w.modality_id = ta.modality_id AND w.status <> 'cancelled'
                        AND w.scheduled_date BETWEEN ? AND ?) AS week_status,
                   (SELECT COUNT(*) FROM workouts w2 LEFT JOIN workout_logs l ON l.workout_id = w2.id
                      WHERE w2.athlete_id = a.id AND w2.modality_id = ta.modality_id AND w2.status <> 'cancelled'
                        AND w2.scheduled_date BETWEEN ? AND ? AND l.id IS NULL) AS removable,
                   (SELECT COUNT(*) FROM workouts w3 JOIN workout_logs l3 ON l3.workout_id = w3.id
                      WHERE w3.athlete_id = a.id AND w3.modality_id = ta.modality_id AND w3.status <> 'cancelled'
                        AND w3.scheduled_date BETWEEN ? AND ?) AS logged
                 FROM trainer_athletes ta JOIN users a ON a.id = ta.athlete_id JOIN modalities m ON m.id = ta.modality_id
                 WHERE ta.trainer_id = ? AND m.slug = ? AND ta.status = 'active' ORDER BY a.name",
                [$monday, $sunday, $monday, $sunday, $monday, $sunday, $u['id'], $mod]
            );
            foreach ($athletes as $a) {
                if ($a['week_status'] === null) {
                    $pending[] = $a;
                } else {
                    $done[] = $a;
                }
            }
        }
        $step = static fn (string $delta): string => (new \DateTimeImmutable($monday))->modify($delta)->format('Y-m-d');
        View::render('trainer/apply', ['title' => 'Aplicar modelos', 'shell' => 'trainer', 'active' => 'aplicar',
            'mods' => $mods, 'mod' => $mod, 'templates' => $templates, 'pending' => $pending, 'done' => $done,
            'monday' => $monday, 'sunday' => $sunday, 'prev' => $step('-7 days'), 'next' => $step('+7 days')]);
    }

    /** Passo 2: monta a semana de cada atleta (qual sessão em cada dia). Não grava nada. */
    public static function plan(): never
    {
        $u = App::require('trainer');
        $t = (int) $u['id'];
        [$mod, $modId, $monday, $back] = self::weekContext($t);

        // tpl[ID_DO_TREINO][] = ID_DO_ATLETA (um atleta só em um treino)
        $picks = [];
        foreach (is_array($_POST['tpl'] ?? null) ? $_POST['tpl'] : [] as $tplId => $athleteIds) {
            if (!ctype_digit((string) $tplId) || !is_array($athleteIds)) {
                continue;
            }
            foreach ($athleteIds as $aid) {
                if (!is_string($aid) || !ctype_digit($aid)) {
                    continue;
                }
                if (isset($picks[(int) $aid]) && $picks[(int) $aid] !== (int) $tplId) {
                    self::fail($back, 'O mesmo atleta foi marcado em mais de um treino.');
                }
                $picks[(int) $aid] = (int) $tplId;
            }
        }
        if (!$picks) {
            self::fail($back, 'Marque pelo menos um atleta em algum treino.');
        }
        $library = self::library($t);
        $athletes = [];
        foreach ($picks as $athleteId => $tplId) {
            $linked = Workout::linkedModalities($t, $athleteId);
            if (!in_array($mod, $linked, true) || ($library[$tplId]['modality'] ?? null) !== $mod) {
                self::fail($back, 'Seleção inválida: atleta ou modelo não disponível.');
            }
            $row = Db::one("SELECT a.name, ta.train_days FROM trainer_athletes ta JOIN users a ON a.id = ta.athlete_id
                            WHERE ta.trainer_id = ? AND ta.athlete_id = ? AND ta.modality_id = ? AND ta.status = 'active'", [$t, $athleteId, $modId]);
            // Pré-monta: a 1ª sessão no 1º dia disponível do atleta, a 2ª no 2º, e assim por diante.
            $days = self::parseDays((string) $row['train_days']);
            $grid = [];
            foreach ($library[$tplId]['sessions'] as $k => $sess) {
                if (isset($days[$k])) {
                    $grid[$days[$k]] = (int) $sess['id'];
                }
            }
            // sessões que este treinador pode prescrever a este atleta: modalidades com vínculo (a do modelo escolhido primeiro)
            $allowed = [$tplId => $library[$tplId]] + array_filter($library, static fn ($x) => in_array($x['modality'], $linked, true));
            $athletes[] = ['id' => $athleteId, 'name' => $row['name'], 'template' => $tplId, 'allowed' => $allowed, 'days' => $days, 'grid' => $grid,
                'unplaced' => max(0, count($library[$tplId]['sessions']) - count($grid)),
                'zones' => (bool) Workout::zones($athleteId)];
        }
        View::render('trainer/plan', ['title' => 'Montar a semana', 'shell' => 'trainer', 'active' => 'aplicar',
            'mod' => $mod, 'monday' => $monday, 'library' => $library, 'athletes' => $athletes, 'back' => $back]);
    }

    /** Envia a semana montada: plan[ID_DO_ATLETA][DIA 1..7] = ID_DA_SESSÃO. */
    public static function applySave(): never
    {
        $u = App::require('trainer');
        $t = (int) $u['id'];
        [$mod, $modId, $monday, $back] = self::weekContext($t);
        $status = Http::input('publish') === '1' ? 'published' : 'draft';

        if (!Http::formComplete()) {
            self::fail($back, 'A montagem ficou grande demais para o servidor e chegou incompleta. Nada foi criado. Envie para menos atletas por vez.');
        }

        $sessionIndex = []; // id da sessão => [modelo, sessão]
        foreach (self::library($t) as $tpl) {
            foreach ($tpl['sessions'] as $sess) {
                $sessionIndex[(int) $sess['id']] = [$tpl, $sess];
            }
        }

        // plan[ID_DO_ATLETA][DIA][] = ID_DA_SESSÃO (quantos treinos quiser por dia, na ordem escolhida)
        $plan = [];
        foreach (is_array($_POST['plan'] ?? null) ? $_POST['plan'] : [] as $athleteId => $week) {
            if (!ctype_digit((string) $athleteId) || !is_array($week)) {
                continue;
            }
            $athleteId = (int) $athleteId;
            $linked = null;
            foreach ($week as $day => $values) {
                foreach (is_array($values) ? $values : [$values] as $sid) {
                    if (!is_string($sid) || $sid === '') {
                        continue;
                    }
                    if (!ctype_digit((string) $day) || (int) $day < 1 || (int) $day > 7 || !ctype_digit($sid) || !isset($sessionIndex[(int) $sid])) {
                        self::fail($back, 'Seleção inválida: sessão não disponível (o modelo pode ter sido alterado). Nada foi criado.');
                    }
                    $linked ??= Workout::linkedModalities($t, $athleteId);
                    if (!in_array($mod, $linked, true) || !in_array($sessionIndex[(int) $sid][0]['modality'], $linked, true)) {
                        self::fail($back, 'Seleção inválida: atleta não vinculado a você em alguma das modalidades escolhidas. Nada foi criado.');
                    }
                    $plan[$athleteId][(int) $day][] = (int) $sid;
                    if (count($plan[$athleteId][(int) $day]) > self::MAX_PER_DAY) {
                        self::fail($back, 'Máximo de ' . self::MAX_PER_DAY . ' treinos no mesmo dia. Nada foi criado.');
                    }
                }
            }
        }
        if (!$plan) {
            self::fail($back, 'Escolha a sessão de pelo menos um dia para algum atleta.');
        }

        $created = 0;
        $skipped = [];
        $notified = [];
        $sunday = (new \DateTimeImmutable($monday))->modify('+6 days')->format('Y-m-d');
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($plan as $athleteId => $week) {
                // Quem já tem treino nesta modalidade e semana não recebe outro (some da lista de pendentes).
                $busy = Db::one("SELECT id FROM workouts WHERE athlete_id = ? AND modality_id = ? AND status <> 'cancelled' AND scheduled_date BETWEEN ? AND ? LIMIT 1",
                    [$athleteId, $modId, $monday, $sunday]);
                if ($busy) {
                    $skipped[] = (string) Db::one('SELECT name FROM users WHERE id = ?', [$athleteId])['name'];
                    continue;
                }
                ksort($week);
                foreach ($week as $day => $sids) {
                    $date = (new \DateTimeImmutable($monday))->modify('+' . ($day - 1) . ' days')->format('Y-m-d');
                    foreach ($sids as $sid) {
                        [$tpl, $sess] = $sessionIndex[$sid];
                        Db::run(
                            'INSERT INTO workouts (athlete_id, modality_id, discipline, scheduled_date, title, notes, status, published_at, created_by, updated_by, source_template_id)
                             VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                            [$athleteId, $tpl['modality_id'], $sess['discipline'], $date, mb_substr($tpl['name'] . ' · ' . $sess['title'], 0, 120), $sess['notes'], $status,
                                $status === 'published' ? date('Y-m-d H:i:s') : null, $t, $t, $tpl['id']]
                        );
                        $wid = (int) $pdo->lastInsertId();
                        Db::run(
                            'INSERT INTO workout_items (workout_id, position, label, sets, reps, load_text, rest_seconds, duration_minutes, distance_km, target, hr_zone, notes)
                             SELECT ?, position, label, sets, reps, load_text, rest_seconds, duration_minutes, distance_km, target, hr_zone, notes
                             FROM workout_template_items WHERE session_id = ? ORDER BY position',
                            [$wid, $sid]
                        );
                        Workout::snapshot($wid, $status === 'published' ? 'published' : 'created', $t);
                        Audit::log('workout_from_template', $t, 'workout', $wid, ['athlete_id' => $athleteId, 'template_id' => (int) $tpl['id']]);
                        $created++;
                        $notified[$athleteId] = true;
                    }
                }
                // Lembra os dias do atleta, se ainda não houver nenhum cadastrado.
                Db::run("UPDATE trainer_athletes SET train_days = ? WHERE trainer_id = ? AND athlete_id = ? AND modality_id = ? AND (train_days IS NULL OR train_days = '')",
                    [implode(',', array_keys($week)), $t, $athleteId, $modId]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        if ($status === 'published') { // um aviso por atleta, depois de gravar tudo
            foreach (array_keys($notified) as $athleteId) {
                Notify::send((int) $athleteId, 'Sua semana de treinos está disponível', 'Semana de ' . date('d/m', strtotime($monday)), '/app/treinos?mes=' . substr($monday, 0, 7));
            }
        }
        $msg = $created . ' treino(s) ' . ($status === 'published' ? 'publicado(s)' : 'salvo(s) como rascunho') . ' na semana de ' . date('d/m/Y', strtotime($monday)) . '.';
        if ($skipped) {
            $msg .= ' Já tinham treino nesta semana e foram ignorados: ' . implode(', ', $skipped) . '.';
        }
        Http::flash($created ? 'ok' : 'err', $msg);
        Http::redirect($back);
    }

    /** "1,3,6" (ou lista) -> [0 => 1, 1 => 3, 2 => 6], sem repetidos e em ordem. @return list<int> */
    public static function parseDays(string|array $raw): array
    {
        $parts = is_array($raw) ? $raw : explode(',', $raw);
        $days = [];
        foreach ($parts as $p) {
            if (is_string($p) && ctype_digit($p) && (int) $p >= 1 && (int) $p <= 7) {
                $days[(int) $p] = (int) $p;
            }
        }
        ksort($days);
        return array_values($days);
    }

    /** Modalidade, semana (segunda) e página de retorno vindas do formulário. @return array{0:string,1:int,2:string,3:string} */
    private static function weekContext(int $trainerId): array
    {
        $mod = Http::input('modality');
        if (!in_array($mod, self::modalities($trainerId), true)) {
            App::notFound();
        }
        $back = '/treinador/modelos/aplicar?modalidade=' . $mod;
        $date = Http::input('scheduled_date');
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date || $d < new \DateTimeImmutable('-1 year') || $d > new \DateTimeImmutable('+2 years')) {
            self::fail($back, 'Informe uma data válida para a semana.');
        }
        $monday = self::monday($date);
        return [$mod, (int) Workout::modalityId($mod), $monday, $back . '&semana=' . $monday];
    }

    /** Modelos ativos do treinador (todas as modalidades), com sessões; só os que têm sessão. @return array<int,array<string,mixed>> */
    private static function library(int $trainerId): array
    {
        $out = [];
        foreach (Db::all('SELECT t.id, t.name, t.level_description, t.modality_id, m.slug AS modality, m.name AS modality_name
                          FROM workout_templates t JOIN modalities m ON m.id = t.modality_id
                          WHERE t.trainer_id = ? AND t.archived_at IS NULL ORDER BY m.id, t.name', [$trainerId]) as $t) {
            $t['sessions'] = self::sessions((int) $t['id']);
            if ($t['sessions']) {
                $out[(int) $t['id']] = $t;
            }
        }
        return $out;
    }

    private static function requireComplete(string $back): void
    {
        if (!Http::formComplete()) {
            self::fail($back, 'O formulário ficou grande demais para o servidor e chegou incompleto. Nada foi salvo. Salve com menos sessões por vez ou avise o suporte.');
        }
    }

    private static function fail(string $back, string $message): never
    {
        Http::flash('err', $message);
        Http::redirect($back);
    }

    /**
     * "Excluir treino" na tela de aplicar: CANCELA (não apaga) os treinos do atleta na modalidade e semana,
     * para ele voltar à lista de pendentes. Treinos já registrados pelo atleta são preservados.
     */
    public static function removeWeek(): never
    {
        $u = App::require('trainer');
        $t = (int) $u['id'];
        [$mod, $modId, $monday, $back] = self::weekContext($t);
        $athleteId = (int) Http::input('athlete_id');
        if (!in_array($mod, Workout::linkedModalities($t, $athleteId), true)) {
            App::notFound();
        }
        $sunday = (new \DateTimeImmutable($monday))->modify('+6 days')->format('Y-m-d');
        $name = (string) (Db::one('SELECT name FROM users WHERE id = ?', [$athleteId])['name'] ?? '');

        $removed = 0;
        $kept = 0;
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $rows = Db::all(
                "SELECT w.id, (SELECT COUNT(*) FROM workout_logs l WHERE l.workout_id = w.id) AS logged
                 FROM workouts w WHERE w.athlete_id = ? AND w.modality_id = ? AND w.status <> 'cancelled' AND w.scheduled_date BETWEEN ? AND ? FOR UPDATE",
                [$athleteId, $modId, $monday, $sunday]
            );
            foreach ($rows as $w) {
                if ((int) $w['logged'] > 0) {
                    $kept++;
                    continue;
                }
                Db::run("UPDATE workouts SET status = 'cancelled', version = version + 1, updated_by = ? WHERE id = ?", [$t, $w['id']]);
                Workout::snapshot((int) $w['id'], 'cancelled', $t);
                Audit::log('workout_cancelled', $t, 'workout', (int) $w['id'], ['athlete_id' => $athleteId]);
                $removed++;
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        if (!$removed && !$kept) {
            self::fail($back, $name . ' não tem treino nesta semana.');
        }
        $msg = $removed . ' treino(s) de ' . $name . ' excluído(s) desta semana.';
        if ($kept) {
            $msg .= ' ' . $kept . ' treino(s) já registrado(s) pelo atleta foram mantidos, por isso ele continua na lista de quem já tem treino.';
        }
        Http::flash($removed ? 'ok' : 'err', $msg);
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
            if ($title === '') {
                $title = "Sessão $n";
            }
            if ($n > self::MAX_SESSIONS) {
                $msgs[] = 'No máximo ' . self::MAX_SESSIONS . ' sessões por modelo.';
                break;
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
            $sessions[] = ['title' => $title, 'discipline' => $discipline, 'notes' => $notes, 'items' => $items];
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
            foreach (['title', 'discipline', 'notes'] as $k) {
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
            Db::run('INSERT INTO workout_template_sessions (template_id, position, title, discipline, notes) VALUES (?,?,?,?,?)',
                [$templateId, $si + 1, $s['title'], $s['discipline'], $s['notes'] === '' ? null : $s['notes']]);
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
