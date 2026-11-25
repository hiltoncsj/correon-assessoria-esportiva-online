<?php
declare(strict_types=1);
namespace Correon;

/** Painel administrativo (Sprint 5). Todas as ações exigem o perfil admin e são auditadas. */
final class Admin
{
    private const PER_PAGE = 50;
    private const INVITE_HOURS = 48;
    private const MODALITIES = ['corrida' => 'Corrida', 'triathlon' => 'Triathlon', 'musculacao' => 'Musculação'];

    private static function admin(): array
    {
        return App::require('admin');
    }

    /** Usuários de um perfil. */
    private static function withRole(string $role): string
    {
        // $role é sempre uma constante do código, nunca entrada do usuário
        return "users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id AND r.slug = '$role'";
    }

    // ---- início ----

    public static function home(): never
    {
        self::admin();
        $monday = Templates::monday(date('Y-m-d'));
        $sunday = date('Y-m-d', strtotime("$monday +6 days"));
        $trainers = self::trainerRows();
        View::render('admin/dashboard', [
            'title' => 'Administração', 'shell' => 'admin', 'active' => 'inicio',
            'counts' => Db::all('SELECT r.name, COUNT(ur.user_id) total FROM roles r LEFT JOIN user_roles ur ON ur.role_id = r.id GROUP BY r.id, r.name ORDER BY r.id'),
            'numbers' => Db::one(
                "SELECT
                   (SELECT COUNT(*) FROM users WHERE status = 'blocked') AS blocked,
                   (SELECT COUNT(*) FROM " . self::withRole('athlete') . " WHERE u.created_at >= (NOW() - INTERVAL 30 DAY)) AS new_athletes,
                   (SELECT COUNT(*) FROM workouts WHERE status = 'published' AND scheduled_date BETWEEN ? AND ?) AS week_workouts,
                   (SELECT COUNT(*) FROM workout_logs WHERE created_at >= (NOW() - INTERVAL 7 DAY)) AS week_logs",
                [$monday, $sunday]
            ),
            'orphans' => Db::all(
                'SELECT u.id, u.name FROM ' . self::withRole('athlete') . "
                 WHERE u.status = 'active' AND NOT EXISTS (SELECT 1 FROM trainer_athletes ta WHERE ta.athlete_id = u.id AND ta.status = 'active')
                 ORDER BY u.created_at DESC LIMIT 50"
            ),
            'trainers' => $trainers,
        ]);
    }

    /** @return list<array<string,mixed>> treinadores com capacidade */
    private static function trainerRows(): array
    {
        $rows = Db::all('SELECT u.id, u.name, u.email, u.status FROM ' . self::withRole('trainer') . ' ORDER BY u.name');
        foreach ($rows as &$r) {
            $r['capacity'] = Capacity::status((int) $r['id']);
        }
        unset($r);
        return $rows;
    }

    // ---- atletas ----

    public static function athletes(): never
    {
        self::admin();
        $q = trim((string) (is_string($_GET['q'] ?? null) ? $_GET['q'] : ''));
        $orphan = ($_GET['sem_treinador'] ?? '') === '1';
        $page = max(1, (int) ($_GET['pagina'] ?? 1));
        $where = ['1 = 1'];
        $params = [];
        if ($q !== '') {
            $like = '%' . addcslashes(mb_substr($q, 0, 100), '%_\\') . '%';
            $where[] = '(u.name LIKE ? OR u.email LIKE ?)';
            array_push($params, $like, $like);
        }
        if ($orphan) {
            $where[] = "NOT EXISTS (SELECT 1 FROM trainer_athletes x WHERE x.athlete_id = u.id AND x.status = 'active')";
        }
        $cond = implode(' AND ', $where);
        $total = (int) Db::one('SELECT COUNT(*) c FROM ' . self::withRole('athlete') . " WHERE $cond", $params)['c'];
        $offset = ($page - 1) * self::PER_PAGE;
        $rows = Db::all(
            "SELECT u.id, u.name, u.email, u.status, u.created_at,
               (SELECT GROUP_CONCAT(CONCAT(t.name, ' (', m.name, ')') ORDER BY m.id SEPARATOR ', ')
                  FROM trainer_athletes ta JOIN users t ON t.id = ta.trainer_id JOIN modalities m ON m.id = ta.modality_id
                 WHERE ta.athlete_id = u.id AND ta.status = 'active') AS trainers
             FROM " . self::withRole('athlete') . " WHERE $cond ORDER BY u.name LIMIT " . self::PER_PAGE . " OFFSET $offset",
            $params
        );
        View::render('admin/athletes', ['title' => 'Atletas', 'shell' => 'admin', 'active' => 'atletas', 'rows' => $rows,
            'q' => $q, 'orphan' => $orphan, 'page' => $page, 'pages' => max(1, (int) ceil($total / self::PER_PAGE)), 'total' => $total]);
    }

    /** @return array<string,mixed> usuário com o perfil pedido, ou 404 */
    private static function userWithRole(int $id, string $role): array
    {
        $u = Db::one('SELECT u.id, u.name, u.email, u.status, u.created_at, u.last_login_at FROM ' . self::withRole($role) . ' WHERE u.id = ?', [$id]);
        if (!$u) {
            App::notFound();
        }
        return $u;
    }

    public static function athlete(int $id): never
    {
        $me = self::admin();
        $athlete = self::userWithRole($id, 'athlete');
        Audit::log('athlete_profile_viewed', (int) $me['id'], 'user', $id);
        View::render('admin/athlete', [
            'title' => $athlete['name'], 'shell' => 'admin', 'active' => 'atletas', 'person' => $athlete,
            'profile' => Athlete::profile($id), 'modalities' => self::MODALITIES, 'contracts' => Billing::ofAthlete($id),
            'links' => Db::all(
                'SELECT ta.trainer_id, t.name AS trainer, m.slug, m.name AS modality, ta.status, ta.started_at, ta.ended_at
                 FROM trainer_athletes ta JOIN users t ON t.id = ta.trainer_id JOIN modalities m ON m.id = ta.modality_id
                 WHERE ta.athlete_id = ? ORDER BY ta.status, m.id, t.name', [$id]),
            'trainers' => array_values(array_filter(self::trainerRows(), static fn ($t) => $t['status'] === 'active')),
            'summary' => Db::one(
                "SELECT COUNT(*) AS planned, SUM(l.outcome IN ('completed','partial')) AS done
                 FROM workouts w LEFT JOIN workout_logs l ON l.workout_id = w.id
                 WHERE w.athlete_id = ? AND w.status = 'published' AND w.scheduled_date BETWEEN (CURDATE() - INTERVAL 28 DAY) AND CURDATE()", [$id]),
        ]);
    }

    public static function assign(int $athleteId): never
    {
        $me = self::admin();
        self::userWithRole($athleteId, 'athlete');
        $trainerId = (int) Http::input('trainer_id');
        $mod = Http::input('modality');
        $r = Assignment::assign($trainerId, $athleteId, $mod, Http::input('reason'), (int) $me['id']);
        if ($r['result'] === Assignment::INVALID) {
            Http::flash('err', 'Escolha um treinador ativo e uma modalidade.');
        } elseif ($r['result'] === Assignment::BLOCKED) {
            $c = Capacity::status($trainerId);
            Http::flash('err', "Bloqueado: este treinador está no limite ({$c['active']} de {$c['limit']} atletas). Para abrir uma exceção, preencha o motivo e envie novamente.");
        } else {
            $c = Capacity::status($trainerId);
            Http::flash('ok', ($r['exception'] ? 'Atribuído com exceção de capacidade registrada.' : 'Treinador atribuído.')
                . ($c['state'] !== 'ok' ? " Atenção: o treinador está com {$c['active']} de {$c['limit']} atletas." : ''));
        }
        Http::redirect('/admin/atletas/' . $athleteId);
    }

    public static function unassign(int $athleteId): never
    {
        $me = self::admin();
        self::userWithRole($athleteId, 'athlete');
        $ok = Assignment::end((int) Http::input('trainer_id'), $athleteId, Http::input('modality'), (int) $me['id']);
        Http::flash($ok ? 'ok' : 'err', $ok ? 'Vínculo encerrado. O histórico foi preservado.' : 'Não havia vínculo ativo.');
        Http::redirect('/admin/atletas/' . $athleteId);
    }

    // ---- treinadores ----

    public static function trainers(): never
    {
        self::admin();
        self::trainersView(['name' => '', 'email' => ''], null, 200);
    }

    /** @param array<string,string> $old */
    private static function trainersView(array $old, ?string $error, int $status): never
    {
        View::render('admin/trainers', ['title' => 'Treinadores', 'shell' => 'admin', 'active' => 'treinadores',
            'rows' => self::trainerRows(), 'old' => $old, 'error' => $error], $status);
    }

    /** Cria o treinador sem senha definida e gera um link (48 h, uso único) para ele criar a própria senha. */
    public static function createTrainer(): never
    {
        $me = self::admin();
        $name = trim(preg_replace('/\s+/u', ' ', Http::input('name')) ?? '');
        $email = strtolower(Http::input('email'));
        $old = ['name' => $name, 'email' => $email];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            self::trainersView($old, 'Informe o nome (2 a 120 caracteres).', 422);
        }
        if (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::trainersView($old, 'Informe um e-mail válido.', 422);
        }
        try {
            $id = Auth::createUser($name, $email, bin2hex(random_bytes(24)), 'trainer', false);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            self::trainersView($old, 'Já existe uma conta com este e-mail.', 422);
        }
        Audit::log('trainer_created', (int) $me['id'], 'user', $id);
        $link = self::sendPasswordLink($id, $email, $name, 'Sua conta de treinador na CorreON foi criada.');
        Http::flash('ok', 'Treinador criado. Link para ele criar a senha (vale ' . self::INVITE_HOURS . ' h, uso único): ' . $link);
        Http::redirect('/admin/treinadores/' . $id);
    }

    public static function trainer(int $id): never
    {
        self::admin();
        $trainer = self::userWithRole($id, 'trainer');
        View::render('admin/trainer', [
            'title' => $trainer['name'], 'shell' => 'admin', 'active' => 'treinadores', 'person' => $trainer,
            'capacity' => Capacity::status($id),
            'presencial' => Db::one('SELECT presencial_limit FROM trainer_settings WHERE trainer_id = ?', [$id])['presencial_limit'] ?? null,
            'athletes' => Db::all(
                "SELECT a.id, a.name, GROUP_CONCAT(m.name ORDER BY m.id SEPARATOR ', ') AS modalities
                 FROM trainer_athletes ta JOIN users a ON a.id = ta.athlete_id JOIN modalities m ON m.id = ta.modality_id
                 WHERE ta.trainer_id = ? AND ta.status = 'active' GROUP BY a.id, a.name ORDER BY a.name", [$id]),
            'exceptions' => Db::all(
                'SELECT e.reason, e.created_at, a.name AS athlete FROM capacity_exceptions e JOIN users a ON a.id = e.athlete_id
                 WHERE e.trainer_id = ? ORDER BY e.id DESC LIMIT 20', [$id]),
            'slots' => Db::all('SELECT weekday, start_time, end_time, mode FROM trainer_availability WHERE trainer_id = ? ORDER BY weekday, start_time', [$id]),
        ]);
    }

    public static function setCapacity(int $id): never
    {
        $me = self::admin();
        self::userWithRole($id, 'trainer');
        $online = Http::input('online_limit');
        $pres = Http::input('presencial_limit');
        if (!ctype_digit($online) || (int) $online < 1 || (int) $online > 1000 || ($pres !== '' && (!ctype_digit($pres) || (int) $pres > 1000))) {
            Http::flash('err', 'Limite online entre 1 e 1000; presencial vazio ou entre 0 e 1000.');
            Http::redirect('/admin/treinadores/' . $id);
        }
        Capacity::setLimits($id, (int) $online, $pres === '' ? null : (int) $pres);
        Audit::log('capacity_changed', (int) $me['id'], 'user', $id, ['online' => (int) $online, 'presencial' => $pres === '' ? null : (int) $pres]);
        Http::flash('ok', 'Capacidade atualizada.');
        Http::redirect('/admin/treinadores/' . $id);
    }

    // ---- contas ----

    private static function backToUser(int $id): never
    {
        $isTrainer = Db::one('SELECT 1 ok FROM ' . self::withRole('trainer') . ' WHERE u.id = ?', [$id]);
        Http::redirect(($isTrainer ? '/admin/treinadores/' : '/admin/atletas/') . $id);
    }

    /** Usuário não administrador (contas de admin só são geridas por linha de comando). @return array<string,mixed> */
    private static function managedUser(int $id): array
    {
        $u = Db::one('SELECT id, name, email, status FROM users WHERE id = ?', [$id]);
        if (!$u || Db::one('SELECT 1 ok FROM ' . self::withRole('admin') . ' WHERE u.id = ?', [$id])) {
            App::notFound();
        }
        return $u;
    }

    public static function setStatus(int $id): never
    {
        $me = self::admin();
        $user = self::managedUser($id);
        $action = Http::input('action');
        if (!in_array($action, ['block', 'unblock'], true)) {
            App::notFound();
        }
        // bloquear encerra as sessões abertas (session_version)
        Db::run('UPDATE users SET status = ?, session_version = session_version + 1 WHERE id = ?', [$action === 'block' ? 'blocked' : 'active', $user['id']]);
        Audit::log($action === 'block' ? 'user_blocked' : 'user_unblocked', (int) $me['id'], 'user', $id);
        Http::flash('ok', $action === 'block' ? 'Conta bloqueada. A pessoa foi desconectada e não consegue entrar.' : 'Conta reativada.');
        self::backToUser($id);
    }

    public static function resetPassword(int $id): never
    {
        $me = self::admin();
        $user = self::managedUser($id);
        if ($user['status'] !== 'active') {
            Http::flash('err', 'Reative a conta antes de gerar o link.');
            self::backToUser($id);
        }
        $link = self::sendPasswordLink($id, $user['email'], $user['name'], 'A administração da CorreON gerou um link para você definir uma nova senha.');
        Audit::log('password_link_generated', (int) $me['id'], 'user', $id);
        Http::flash('ok', 'Link para definir a senha (vale ' . self::INVITE_HOURS . ' h, uso único): ' . $link);
        self::backToUser($id);
    }

    /** Gera o token (só o hash vai ao banco), envia por e-mail e devolve o link para o admin repassar. */
    private static function sendPasswordLink(int $userId, string $email, string $name, string $intro): string
    {
        $token = bin2hex(random_bytes(32));
        Db::run('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$userId]);
        Db::run('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?,?, NOW() + INTERVAL ' . self::INVITE_HOURS . ' HOUR)',
            [$userId, hash('sha256', $token)]);
        $link = rtrim((string) Env::get('APP_URL', ''), '/') . '/redefinir-senha/' . $token;
        Mailer::send($email, 'CorreON — defina sua senha',
            "Olá, $name.\n\n$intro\nCrie sua senha pelo link abaixo (válido por " . self::INVITE_HOURS . " horas, uso único):\n$link\n");
        return $link;
    }

    // ---- relatórios e auditoria ----

    public static function reports(): never
    {
        self::admin();
        View::render('admin/reports', [
            'title' => 'Relatórios', 'shell' => 'admin', 'active' => 'relatorios',
            'trainers' => Db::all(
                "SELECT t.name,
                   (SELECT COUNT(DISTINCT athlete_id) FROM trainer_athletes WHERE trainer_id = t.id AND status = 'active') AS athletes,
                   COUNT(w.id) AS planned,
                   SUM(l.outcome IN ('completed','partial')) AS done,
                   SUM(w.id IS NOT NULL AND l.id IS NULL AND w.scheduled_date < CURDATE()) AS missing
                 FROM (SELECT u.id, u.name FROM " . self::withRole('trainer') . ") t
                 LEFT JOIN trainer_athletes ta ON ta.trainer_id = t.id AND ta.status = 'active'
                 LEFT JOIN workouts w ON w.athlete_id = ta.athlete_id AND w.modality_id = ta.modality_id AND w.status = 'published'
                      AND w.scheduled_date BETWEEN (CURDATE() - INTERVAL 28 DAY) AND CURDATE()
                 LEFT JOIN workout_logs l ON l.workout_id = w.id
                 GROUP BY t.id, t.name ORDER BY t.name"
            ),
            'modalities' => Db::all(
                "SELECT m.name, COUNT(DISTINCT ta.athlete_id) AS athletes, COUNT(DISTINCT ta.trainer_id) AS trainers
                 FROM modalities m LEFT JOIN trainer_athletes ta ON ta.modality_id = m.id AND ta.status = 'active'
                 GROUP BY m.id, m.name ORDER BY m.id"
            ),
        ]);
    }

    public static function audit(): never
    {
        self::admin();
        $action = (string) (is_string($_GET['acao'] ?? null) ? $_GET['acao'] : '');
        $day = (string) (is_string($_GET['dia'] ?? null) ? $_GET['dia'] : '');
        $page = max(1, (int) ($_GET['pagina'] ?? 1));
        $where = ['1 = 1'];
        $params = [];
        if ($action !== '') {
            $where[] = 'l.action = ?';
            $params[] = $action;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $day);
        if ($d && $d->format('Y-m-d') === $day) {
            $where[] = 'l.created_at >= ? AND l.created_at < ? + INTERVAL 1 DAY';
            array_push($params, $day, $day);
        } else {
            $day = '';
        }
        $cond = implode(' AND ', $where);
        $total = (int) Db::one("SELECT COUNT(*) c FROM audit_log l WHERE $cond", $params)['c'];
        $offset = ($page - 1) * self::PER_PAGE;
        View::render('admin/audit', [
            'title' => 'Auditoria', 'shell' => 'admin', 'active' => 'auditoria', 'action' => $action, 'day' => $day,
            'page' => $page, 'pages' => max(1, (int) ceil($total / self::PER_PAGE)), 'total' => $total,
            'actions' => array_column(Db::all('SELECT DISTINCT action FROM audit_log ORDER BY action'), 'action'),
            'rows' => Db::all(
                "SELECT l.created_at, l.action, l.entity_type, l.entity_id, l.ip, l.details, u.name AS who
                 FROM audit_log l LEFT JOIN users u ON u.id = l.user_id WHERE $cond ORDER BY l.id DESC LIMIT " . self::PER_PAGE . " OFFSET $offset",
                $params
            ),
        ]);
    }
}
