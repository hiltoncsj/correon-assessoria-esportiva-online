<?php
declare(strict_types=1);
namespace Correon;

/** Área do atleta (Sprint 2). Todo acesso é ao PRÓPRIO usuário logado: nenhuma rota recebe id de usuário. */
final class Athlete
{
    private const STATES = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];


    public static function dashboard(): never
    {
        $u = App::require('athlete');
        $id = (int) $u['id'];
        $profile = self::profile($id);
        $meta = Db::one('SELECT created_at, last_login_at FROM users WHERE id = ?', [$id]) ?? [];
        $next = Db::all(
            "SELECT w.id, w.title, w.scheduled_date, w.discipline, m.name AS modality
             FROM workouts w JOIN modalities m ON m.id = w.modality_id LEFT JOIN workout_logs l ON l.workout_id = w.id
             WHERE w.athlete_id = ? AND w.status = 'published' AND w.scheduled_date >= CURDATE() AND l.id IS NULL
             ORDER BY w.scheduled_date, w.id LIMIT 3",
            [$id]
        );
        $stats = Db::one(
            "SELECT
               (SELECT COUNT(*) FROM workouts w LEFT JOIN workout_logs l ON l.workout_id = w.id
                  WHERE w.athlete_id = ? AND w.status = 'published' AND w.scheduled_date >= CURDATE() AND l.id IS NULL) AS upcoming,
               (SELECT COUNT(*) FROM workout_logs WHERE athlete_id = ? AND outcome IN ('completed','partial')) AS done",
            [$id, $id]
        ) ?? ['upcoming' => 0, 'done' => 0];
        View::render('athlete/dashboard', [
            'title' => 'Minha área', 'shell' => 'athlete', 'active' => 'home',
            'u' => $u, 'meta' => $meta, 'completeness' => self::completeness($profile),
            'next' => $next, 'stats' => $stats, 'trainers' => self::trainerLinks($id),
            'modalities' => self::activeModalities($id), 'contracts' => Billing::ofAthlete($id),
        ]);
    }

    /** @return list<array<string,mixed>> */
    private static function trainerLinks(int $athleteId): array
    {
        return Db::all(
            "SELECT t.name AS trainer, m.name AS modality FROM trainer_athletes ta
             JOIN users t ON t.id = ta.trainer_id JOIN modalities m ON m.id = ta.modality_id
             WHERE ta.athlete_id = ? AND ta.status = 'active' ORDER BY m.id, t.name",
            [$athleteId]
        );
    }

    public static function trainers(): never
    {
        $u = App::require('athlete');
        View::render('athlete/trainers', ['title' => 'Meu treinador', 'shell' => 'athlete', 'active' => 'treinador',
            'links' => self::trainerLinks((int) $u['id'])]);
    }

    /** Treinos publicados do mês (?mes=AAAA-MM). */
    public static function workouts(): never
    {
        $u = App::require('athlete');
        $mes = (string) ($_GET['mes'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
            $mes = date('Y-m');
        }
        $first = new \DateTimeImmutable($mes . '-01');
        $rows = Db::all(
            "SELECT w.id, w.title, w.scheduled_date, w.discipline, m.name AS modality, l.outcome
             FROM workouts w JOIN modalities m ON m.id = w.modality_id LEFT JOIN workout_logs l ON l.workout_id = w.id
             WHERE w.athlete_id = ? AND w.status = 'published' AND w.scheduled_date BETWEEN ? AND ?
             ORDER BY w.scheduled_date, w.id",
            [$u['id'], $first->format('Y-m-d'), $first->modify('last day of this month')->format('Y-m-d')]
        );
        $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        View::render('athlete/workouts', [
            'title' => 'Meus treinos', 'shell' => 'athlete', 'active' => 'treinos', 'rows' => $rows,
            'monthLabel' => $meses[(int) $first->format('n') - 1] . ' de ' . $first->format('Y'),
            'prev' => $first->modify('-1 month')->format('Y-m'), 'next' => $first->modify('+1 month')->format('Y-m'),
        ]);
    }

    /** Treino publicado do próprio atleta; qualquer outro caso = 404. @return array<string,mixed> */
    private static function ownPublished(array $u, int $id): array
    {
        $w = Workout::find($id);
        if (!$w || (int) $w['athlete_id'] !== (int) $u['id'] || $w['status'] !== 'published') {
            App::notFound();
        }
        return $w;
    }

    /** @param array<string,string> $errors @param array<string,mixed> $old */
    public static function workout(int $id, array $errors = [], array $old = [], int $status = 200): never
    {
        $u = App::require('athlete');
        $w = self::ownPublished($u, $id);
        $log = Workout::log($id);
        View::render('athlete/workout', [
            'title' => $w['title'], 'shell' => 'athlete', 'active' => 'treinos', 'w' => $w,
            'items' => Workout::items($id), 'log' => $log, 'errors' => $errors, 'zones' => Workout::zonesForWorkout($id, (int) $u['id']),
            'old' => $old ?: ($log ?: []), 'canLog' => $w['scheduled_date'] <= date('Y-m-d'),
        ], $status);
    }

    public static function logSave(int $id): never
    {
        $u = App::require('athlete');
        $w = self::ownPublished($u, $id);
        if ($w['scheduled_date'] > date('Y-m-d')) {
            Http::flash('err', 'Você só pode registrar o treino a partir do dia dele.');
            Http::redirect('/app/treinos/' . $id);
        }
        $in = [];
        foreach (['outcome', 'performed_on', 'duration_minutes', 'distance_km', 'effort', 'notes'] as $k) {
            $in[$k] = Http::input($k);
        }
        $errors = [];
        if (!isset(Workout::OUTCOMES[$in['outcome']])) {
            $errors['outcome'] = 'Escolha como foi o treino.';
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $in['performed_on']);
        if (!$d || $d->format('Y-m-d') !== $in['performed_on'] || $in['performed_on'] > date('Y-m-d')
            || $d < (new \DateTimeImmutable($w['scheduled_date']))->modify('-30 days')) {
            $errors['performed_on'] = 'Data inválida (não pode ser futura).';
        }
        $skipped = $in['outcome'] === 'skipped';
        $dur = $dist = $eff = null;
        if (!$skipped) {
            if ($in['duration_minutes'] !== '') {
                if (!ctype_digit($in['duration_minutes']) || (int) $in['duration_minutes'] < 1 || (int) $in['duration_minutes'] > 1440) {
                    $errors['duration_minutes'] = 'Duração entre 1 e 1440 minutos.';
                } else {
                    $dur = (int) $in['duration_minutes'];
                }
            }
            if ($in['distance_km'] !== '' && !Workout::isStrength($w['discipline'])) {
                $km = str_replace(',', '.', $in['distance_km']);
                if (!preg_match('/^\d{1,4}(\.\d{1,2})?$/', $km) || (float) $km <= 0) {
                    $errors['distance_km'] = 'Distância inválida (km, ex.: 5 ou 5,5).';
                } else {
                    $dist = $km;
                }
            }
            if ($in['effort'] !== '') {
                if (!ctype_digit($in['effort']) || (int) $in['effort'] < 1 || (int) $in['effort'] > 10) {
                    $errors['effort'] = 'Esforço percebido de 1 a 10.';
                } else {
                    $eff = (int) $in['effort'];
                }
            }
        }
        if (mb_strlen($in['notes']) > 500) {
            $errors['notes'] = 'Máximo de 500 caracteres.';
        }
        if ($errors) {
            self::workout($id, $errors, $in, 422);
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $existing = Workout::log($id);
            if ($existing) { // preserva a versão anterior do registro
                Db::run('INSERT INTO workout_log_revisions (log_id, snapshot) VALUES (?,?)', [$existing['id'], json_encode($existing, JSON_UNESCAPED_UNICODE)]);
            }
            Db::run(
                'INSERT INTO workout_logs (workout_id, athlete_id, outcome, performed_on, duration_minutes, distance_km, effort, notes, zones_snapshot)
                 VALUES (?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE outcome=VALUES(outcome), performed_on=VALUES(performed_on), duration_minutes=VALUES(duration_minutes),
                   distance_km=VALUES(distance_km), effort=VALUES(effort), notes=VALUES(notes)',
                [$id, $u['id'], $in['outcome'], $in['performed_on'], $dur, $dist, $eff, $in['notes'] === '' ? null : $in['notes'],
                    json_encode(Workout::zones((int) $u['id']))]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        Audit::log($existing ? 'workout_log_updated' : 'workout_logged', (int) $u['id'], 'workout', $id);
        Http::flash('ok', 'Registro salvo.');
        Http::redirect('/app/treinos/' . $id);
    }

    public static function billing(): never
    {
        $u = App::require('athlete');
        $contracts = Billing::ofAthlete((int) $u['id']); // somente o próprio usuário da sessão
        $charges = [];
        foreach (Db::all('SELECT c.* FROM charges c JOIN contracts k ON k.id = c.contract_id WHERE k.athlete_id = ? ORDER BY c.seq', [$u['id']]) as $ch) {
            $charges[(int) $ch['contract_id']][] = $ch;
        }
        View::render('athlete/billing', ['title' => 'Assinaturas', 'shell' => 'athlete', 'active' => 'assinaturas', 'contracts' => $contracts, 'charges' => $charges]);
    }

    /** @return list<string> modalidades cobertas por contratos vigentes */
    private static function activeModalities(int $athleteId): array
    {
        $out = [];
        foreach (Db::all("SELECT product FROM contracts WHERE athlete_id = ? AND status = 'active' AND starts_on <= CURDATE() AND ends_on >= CURDATE()", [$athleteId]) as $r) {
            foreach (Billing::modalitiesOf($r['product']) as $m) {
                $out[$m] = Billing::PRODUCTS[$m];
            }
        }
        return array_values($out);
    }

    public static function modalities(): never
    {
        $u = App::require('athlete');
        View::render('athlete/modalities', ['title' => 'Minhas modalidades', 'shell' => 'athlete', 'active' => 'modalidades',
            'modalities' => self::activeModalities((int) $u['id'])]);
    }

    public static function profileForm(): never
    {
        $u = App::require('athlete');
        View::render('athlete/profile', [
            'title' => 'Meu perfil', 'shell' => 'athlete', 'active' => 'perfil', 'u' => $u,
            'old' => self::profile((int) $u['id']), 'errors' => [], 'states' => self::STATES, 'zones' => Workout::zones((int) $u['id']), 'maxHr' => Workout::maxHr((int) $u['id']),
        ]);
    }

    public static function profileSave(): never
    {
        $u = App::require('athlete');
        $in = [];
        foreach (['name', 'phone', 'birth_date', 'city', 'state', 'goal', 'sports_background', 'emergency_name', 'emergency_phone'] as $k) {
            $in[$k] = preg_replace('/[ \t]+/u', ' ', Http::input($k)) ?? '';
        }
        $errors = [];

        if (mb_strlen($in['name']) < 2 || mb_strlen($in['name']) > 120) {
            $errors['name'] = 'Informe seu nome (2 a 120 caracteres).';
        }
        $phone = self::digits($in['phone']);
        if ($in['phone'] !== '' && !self::validPhone($phone)) {
            $errors['phone'] = 'Telefone inválido. Use DDD + número.';
        }
        $ephone = self::digits($in['emergency_phone']);
        if ($in['emergency_phone'] !== '' && !self::validPhone($ephone)) {
            $errors['emergency_phone'] = 'Telefone inválido. Use DDD + número.';
        }
        if ($in['birth_date'] !== '') {
            $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $in['birth_date']);
            $ok = $d && $d->format('Y-m-d') === $in['birth_date'];
            if (!$ok || $d > new \DateTimeImmutable('today') || $d < new \DateTimeImmutable('1900-01-01')) {
                $errors['birth_date'] = 'Data de nascimento inválida.';
            } elseif ($d > new \DateTimeImmutable('-13 years')) {
                $errors['birth_date'] = 'O cadastro é permitido a partir de 13 anos.';
            }
        }
        if (mb_strlen($in['city']) > 80) {
            $errors['city'] = 'Cidade muito longa (máx. 80).';
        }
        if ($in['state'] !== '' && !in_array($in['state'], self::STATES, true)) {
            $errors['state'] = 'Estado inválido.';
        }
        if (mb_strlen($in['goal']) > 500) {
            $errors['goal'] = 'Máximo de 500 caracteres.';
        }
        if (mb_strlen($in['sports_background']) > 1000) {
            $errors['sports_background'] = 'Máximo de 1000 caracteres.';
        }
        if (mb_strlen($in['emergency_name']) > 120) {
            $errors['emergency_name'] = 'Máximo de 120 caracteres.';
        }
        if (($in['emergency_name'] === '') !== ($ephone === '')) {
            $errors['emergency_name'] = 'Informe nome e telefone do contato de emergência, ou deixe os dois em branco.';
        }

        if ($errors) {
            View::render('athlete/profile', [
                'title' => 'Meu perfil', 'shell' => 'athlete', 'active' => 'perfil', 'u' => $u,
                'old' => $in, 'errors' => $errors, 'states' => self::STATES, 'zones' => Workout::zones((int) $u['id']), 'maxHr' => Workout::maxHr((int) $u['id']),
            ], 422);
        }

        $n = static fn (string $v): ?string => $v === '' ? null : $v;
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            Db::run('UPDATE users SET name = ? WHERE id = ?', [$in['name'], $u['id']]);
            Db::run(
                'INSERT INTO athlete_profiles (user_id, phone, birth_date, city, state, goal, sports_background, emergency_name, emergency_phone)
                 VALUES (?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE phone=VALUES(phone), birth_date=VALUES(birth_date), city=VALUES(city), state=VALUES(state),
                   goal=VALUES(goal), sports_background=VALUES(sports_background), emergency_name=VALUES(emergency_name), emergency_phone=VALUES(emergency_phone)',
                [$u['id'], $n($phone), $n($in['birth_date']), $n($in['city']), $n($in['state']), $n($in['goal']),
                    $n($in['sports_background']), $n($in['emergency_name']), $n($ephone)]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        // Auditoria registra só QUAIS campos existem, nunca os valores pessoais.
        Audit::log('profile_updated', (int) $u['id'], 'athlete_profile', (int) $u['id'], ['fields' => array_keys(array_filter($in, static fn ($v) => $v !== ''))]);
        Http::flash('ok', 'Perfil atualizado.');
        Http::redirect('/app/perfil');
    }

    public static function passwordForm(): never
    {
        $u = App::require('athlete');
        View::render('athlete/password', ['title' => 'Alterar senha', 'shell' => 'athlete', 'active' => 'senha', 'u' => $u, 'error' => null]);
    }

    public static function passwordSave(): never
    {
        $u = App::require('athlete');
        $id = (int) $u['id'];
        $render = static fn (string $err, int $status) => View::render('athlete/password', [
            'title' => 'Alterar senha', 'shell' => 'athlete', 'active' => 'senha', 'u' => $u, 'error' => $err,
        ], $status);

        if (RateLimiter::tooMany('pwchange', (string) $id, 5, 900)) {
            $render('Muitas tentativas. Aguarde alguns minutos.', 429);
        }
        // Decisão do cliente: usuário já logado troca a senha sem digitar a atual.
        // Compensações: limite de trocas, desconexão das outras sessões, auditoria e aviso por e-mail.
        $new = (string) ($_POST['password'] ?? '');
        $row = Db::one('SELECT password_hash FROM users WHERE id = ?', [$id]);
        $err = Auth::passwordError($new) ?? ($new !== (string) ($_POST['password2'] ?? '') ? 'As senhas não conferem.' : null);
        if ($err === null && $row && password_verify($new, $row['password_hash'])) {
            $err = 'A nova senha deve ser diferente da atual.';
        }
        if ($err !== null) {
            $render($err, 422);
        }
        RateLimiter::hit('pwchange', (string) $id);
        Db::run('UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $id]);
        Auth::login($id); // nova sessão com a versão atual; as outras sessões deixam de valer
        Audit::log('password_changed', $id, 'user', $id);
        Mailer::send((string) $u['email'], 'CorreON — sua senha foi alterada',
            "Olá, {$u['name']}.

A senha da sua conta CorreON foi alterada em " . date('d/m/Y H:i') . ".
"
            . "Se não foi você, recupere o acesso em " . rtrim((string) Env::get('APP_URL', ''), '/') . "/recuperar-senha e avise a CorreON.");
        Http::flash('ok', 'Senha alterada. Os outros dispositivos foram desconectados.');
        Http::redirect('/app/senha');
    }

    // ---- auxiliares ----

    /** @return array<string,string> */
    public static function profile(int $userId): array
    {
        $row = Db::one(
            'SELECT u.name, p.phone, p.birth_date, p.city, p.state, p.goal, p.sports_background, p.emergency_name, p.emergency_phone
             FROM users u LEFT JOIN athlete_profiles p ON p.user_id = u.id WHERE u.id = ?',
            [$userId]
        ) ?? [];
        return array_map(static fn ($v) => (string) ($v ?? ''), $row);
    }

    /** Percentual real de campos-chave preenchidos. @param array<string,string> $p */
    public static function completeness(array $p): int
    {
        $keys = ['phone', 'birth_date', 'city', 'state', 'goal', 'sports_background', 'emergency_name', 'emergency_phone'];
        $filled = count(array_filter($keys, static fn ($k) => ($p[$k] ?? '') !== ''));
        return (int) round($filled / count($keys) * 100);
    }

    private static function digits(string $s): string
    {
        return preg_replace('/\D+/', '', $s) ?? '';
    }

    private static function validPhone(string $d): bool
    {
        return preg_match('/^[1-9]{2}9?\d{8}$/', $d) === 1;
    }
}
