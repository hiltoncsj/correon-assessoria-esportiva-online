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
            $r['modalities'] = Assignment::modalities((int) $r['id']);
        }
        unset($r);
        return $rows;
    }

    // ---- atletas ----

    public static function athletes(): never
    {
        self::admin();
        self::athletesView(['name' => '', 'email' => '', 'trainer_id' => '', 'modality' => ''], null, 200);
    }

    /**
     * Cadastro de atleta pela administração, com senha provisória. O aceite dos Termos é feito pelo próprio
     * atleta no primeiro acesso. Treinador e modalidade são opcionais.
     */
    public static function createAthlete(): never
    {
        $me = self::admin();
        $old = [
            'name' => trim(preg_replace('/\s+/u', ' ', Http::input('name')) ?? ''),
            'email' => strtolower(Http::input('email')),
            'trainer_id' => Http::input('trainer_id'),
            'modality' => Http::input('modality'),
        ];
        $temp = (string) ($_POST['temp_password'] ?? '');
        if (mb_strlen($old['name']) < 2 || mb_strlen($old['name']) > 120) {
            self::athletesView($old, 'Informe o nome (2 a 120 caracteres).', 422);
        }
        if (strlen($old['email']) > 190 || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            self::athletesView($old, 'Informe um e-mail válido.', 422);
        }
        if (($err = Auth::passwordError($temp)) !== null) {
            self::athletesView($old, 'Senha provisória: ' . $err, 422);
        }
        if (($old['trainer_id'] === '') !== ($old['modality'] === '')) {
            self::athletesView($old, 'Para já vincular, escolha a modalidade e o treinador (ou deixe os dois em branco).', 422);
        }
        try {
            $id = Auth::createUser($old['name'], $old['email'], $temp, 'athlete', false);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            self::athletesView($old, 'Já existe uma conta com este e-mail.', 422);
        }
        Db::run('UPDATE users SET must_change_password = 1 WHERE id = ?', [$id]);
        Audit::log('athlete_created', (int) $me['id'], 'user', $id);

        $msg = 'Atleta cadastrado. Informe o e-mail e a senha provisória: no primeiro acesso ele cria a própria senha e aceita os termos.';
        $type = 'ok';
        if ($old['trainer_id'] !== '') {
            $r = Assignment::assign((int) $old['trainer_id'], $id, $old['modality'], '', (int) $me['id']);
            if ($r['result'] === Assignment::OK) {
                $msg .= ' Treinador vinculado.';
            } else {
                $type = 'err';
                $msg = 'Atleta cadastrado, mas o treinador NÃO foi vinculado ('
                    . ($r['result'] === Assignment::BLOCKED ? 'está no limite de atletas; atribua abaixo informando o motivo da exceção'
                        : ($r['result'] === Assignment::NOT_ATTENDED ? 'não atende essa modalidade' : 'seleção inválida')) . ').';
            }
        }
        Http::flash($type, $msg);
        Http::redirect('/admin/atletas/' . $id);
    }

    /** @param array<string,string> $old */
    private static function athletesView(array $old, ?string $error, int $status): never
    {
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
            'old' => $old, 'error' => $error, 'modalities' => self::MODALITIES,
            'trainers' => array_values(array_filter(self::trainerRows(), static fn ($t) => $t['status'] === 'active' && $t['modalities'])),
            'q' => $q, 'orphan' => $orphan, 'page' => $page, 'pages' => max(1, (int) ceil($total / self::PER_PAGE)), 'total' => $total], $status);
    }

    /** @return array<string,mixed> usuário com o perfil pedido, ou 404 */
    private static function userWithRole(int $id, string $role): array
    {
        $u = Db::one('SELECT u.id, u.name, u.email, u.status, u.created_at, u.last_login_at, u.must_change_password FROM ' . self::withRole($role) . ' WHERE u.id = ?', [$id]);
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
            'goals' => Goals::ofAthlete($id),
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
        if ($r['result'] === Assignment::NOT_ATTENDED) {
            Http::flash('err', 'Este treinador não atende essa modalidade. Marque a modalidade na página dele ou escolha outro treinador.');
        } elseif ($r['result'] === Assignment::INVALID) {
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

    /** @return list<string> */
    private static function postedModalities(): array
    {
        $raw = $_POST['modalities'] ?? [];
        return array_values(array_intersect(array_keys(self::MODALITIES), is_array($raw) ? array_filter($raw, 'is_string') : []));
    }

    public static function setModalities(int $id): never
    {
        $me = self::admin();
        self::userWithRole($id, 'trainer');
        $err = Assignment::setModalities($id, self::postedModalities(), (int) $me['id']);
        Http::flash($err === null ? 'ok' : 'err', $err ?? 'Modalidades do treinador atualizadas.');
        Http::redirect('/admin/treinadores/' . $id);
    }

    /** @param array<string,string> $old */
    private static function trainersView(array $old, ?string $error, int $status): never
    {
        View::render('admin/trainers', ['title' => 'Treinadores', 'shell' => 'admin', 'active' => 'treinadores',
            'rows' => self::trainerRows(), 'old' => $old, 'error' => $error, 'modalities' => self::MODALITIES, 'checked' => self::postedModalities()], $status);
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
        $temp = (string) ($_POST['temp_password'] ?? '');
        $mods = self::postedModalities();
        if ($temp !== '' && ($err = Auth::passwordError($temp)) !== null) {
            self::trainersView($old, 'Senha provisória: ' . $err, 422);
        }
        try {
            $id = Auth::createUser($name, $email, $temp !== '' ? $temp : bin2hex(random_bytes(24)), 'trainer', false);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            self::trainersView($old, 'Já existe uma conta com este e-mail.', 422);
        }
        if ($temp !== '') {
            Db::run('UPDATE users SET must_change_password = 1 WHERE id = ?', [$id]);
        }
        Audit::log('trainer_created', (int) $me['id'], 'user', $id, ['temporary_password' => $temp !== '']);
        Assignment::setModalities($id, $mods, (int) $me['id']);
        if ($temp !== '') {
            Http::flash('ok', 'Treinador criado com a senha provisória. Informe a ele o e-mail e a senha; no primeiro acesso ele terá de criar a própria.');
            Http::redirect('/admin/treinadores/' . $id);
        }
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
            'modalities' => self::MODALITIES, 'attends' => Assignment::modalities($id),
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

    /**
     * Excluir conta de TREINADOR. Sem nenhum registro: apaga de verdade. Com histórico (treinos, modelos,
     * vínculos antigos...): desativa a conta, tira das listas, libera o e-mail e PRESERVA o histórico.
     * Com atletas ou Personal ativos, recusa: primeiro é preciso encerrar.
     */
    private static function deleteTrainer(int $id): never
    {
        $me = self::admin();
        $trainer = self::userWithRole($id, 'trainer'); // atletas e administradores: 404
        if (Db::one('SELECT 1 ok FROM ' . self::withRole('admin') . ' WHERE u.id = ?', [$id])
            || Db::one('SELECT 1 ok FROM ' . self::withRole('athlete') . ' WHERE u.id = ?', [$id])) {
            App::notFound();
        }
        $links = (int) Db::one("SELECT COUNT(DISTINCT athlete_id) c FROM trainer_athletes WHERE trainer_id = ? AND status = 'active'", [$id])['c'];
        $pt = Personal::activeAthletes($id);
        $sessions = (int) Db::one("SELECT COUNT(*) c FROM pt_sessions WHERE trainer_id = ? AND status IN ('requested','approved') AND starts_at > NOW()", [$id])['c'];
        $orders = (int) Db::one('SELECT COUNT(*) c FROM orders WHERE athlete_id = ? AND cancelled_at IS NULL AND (paid_at IS NULL OR delivered_at IS NULL)', [$id])['c'];
        if ($orders) {
            Http::flash('err', "Não é possível excluir: este treinador tem $orders pedido(s) da loja em aberto.");
            Http::redirect('/admin/treinadores/' . $id);
        }
        if ($links || $pt || $sessions) {
            Http::flash('err', "Não é possível excluir: este treinador tem $links atleta(s) vinculado(s), $pt com Personal ativo e $sessions sessão(ões) pendente(s). Encerre os vínculos e o Personal primeiro (ou apenas bloqueie a conta).");
            Http::redirect('/admin/treinadores/' . $id);
        }

        $erased = true;
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            foreach (['password_resets', 'consents', 'notifications', 'user_roles'] as $table) {
                Db::run("DELETE FROM $table WHERE user_id = ?", [$id]); // nomes de tabela constantes
            }
            Db::run('DELETE FROM trainer_availability WHERE trainer_id = ?', [$id]);
            Db::run('DELETE FROM trainer_settings WHERE trainer_id = ?', [$id]);
            Db::run('DELETE FROM trainer_modalities WHERE trainer_id = ?', [$id]);
            Db::run('DELETE FROM users WHERE id = ?', [$id]);
            $pdo->commit();
        } catch (\PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            $erased = false; // há registros que dependem da conta
        }
        if (!$erased) {
            $pdo->beginTransaction();
            try {
                Db::run('DELETE FROM user_roles WHERE user_id = ?', [$id]);
                Db::run('DELETE FROM password_resets WHERE user_id = ?', [$id]);
                Db::run('DELETE FROM trainer_availability WHERE trainer_id = ?', [$id]);
                Db::run('DELETE FROM trainer_modalities WHERE trainer_id = ?', [$id]);
                Db::run(
                    "UPDATE users SET status = 'blocked', deleted_at = NOW(), session_version = session_version + 1, must_change_password = 0,
                       name = ?, email = ?, password_hash = ? WHERE id = ?",
                    [mb_substr($trainer['name'], 0, 108) . ' (excluído)', "excluido-$id@conta.invalid", password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT), $id]
                );
                $pdo->commit();
            } catch (\Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
        Audit::log('trainer_deleted', (int) $me['id'], 'user', $id, ['history_kept' => !$erased]);
        Http::flash('ok', $erased ? 'Conta excluída.' : 'Conta excluída. Os treinos e registros antigos foram mantidos no histórico, e o e-mail ficou livre.');
        Http::redirect('/admin/treinadores');
    }

    /** Botão "Excluir conta": atletas ou treinadores. Contas de administrador nunca (404). */
    public static function deleteUser(int $id): never
    {
        self::admin();
        $is = static fn (string $role) => Db::one('SELECT 1 ok FROM ' . self::withRole($role) . ' WHERE u.id = ?', [$id]) !== null;
        if ($is('admin')) {
            App::notFound();
        }
        $is('athlete') && !$is('trainer') ? self::deleteAthlete($id) : self::deleteTrainer($id);
    }

    /**
     * Excluir conta de ATLETA. Recusa se houver contrato vigente, cobrança em aberto, Personal ativo ou pedido
     * em aberto. Sem histórico: apaga. Com histórico (treinos, contratos, pagamentos, pedidos, vínculos):
     * desativa, libera o e-mail, apaga os dados do perfil e retira a autorização de publicação.
     */
    private static function deleteAthlete(int $id): never
    {
        $me = self::admin();
        $athlete = self::userWithRole($id, 'athlete');
        $count = static fn (string $sql) => (int) Db::one($sql, [$id])['c'];
        $pending = array_filter([
            'contrato(s) vigente(s)' => $count("SELECT COUNT(*) c FROM contracts WHERE athlete_id = ? AND status = 'active' AND ends_on >= CURDATE()"),
            'cobrança(s) em aberto' => $count("SELECT COUNT(*) c FROM charges h JOIN contracts k ON k.id = h.contract_id WHERE k.athlete_id = ? AND h.status = 'open'"),
            'pacote(s) de Personal ativo(s)' => $count("SELECT COUNT(*) c FROM pt_packages WHERE athlete_id = ? AND status = 'active'"),
            'sessão(ões) de Personal marcada(s)' => $count("SELECT COUNT(*) c FROM pt_sessions WHERE athlete_id = ? AND status IN ('requested','approved') AND starts_at > NOW()"),
            'pedido(s) da loja em aberto' => $count('SELECT COUNT(*) c FROM orders WHERE athlete_id = ? AND cancelled_at IS NULL AND (paid_at IS NULL OR delivered_at IS NULL)'),
        ]);
        if ($pending) {
            $list = implode(', ', array_map(static fn ($label, $n) => "$n $label", array_keys($pending), $pending));
            Http::flash('err', "Não é possível excluir: este atleta tem $list. Encerre isso primeiro (ou apenas bloqueie a conta).");
            Http::redirect('/admin/atletas/' . $id);
        }

        $erased = true;
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            foreach (['password_resets', 'consents', 'notifications', 'user_roles', 'athlete_profiles'] as $table) {
                Db::run("DELETE FROM $table WHERE user_id = ?", [$id]); // nomes de tabela constantes
            }
            foreach (['athlete_hr_zones', 'athlete_hr_settings', 'goal_participants', 'athlete_goals'] as $table) {
                Db::run("DELETE FROM $table WHERE athlete_id = ?", [$id]);
            }
            Db::run('DELETE FROM users WHERE id = ?', [$id]);
            $pdo->commit();
        } catch (\PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            $erased = false; // há registros que dependem da conta
        }
        if (!$erased) {
            $pdo->beginTransaction();
            try {
                Db::run("UPDATE trainer_athletes SET status = 'ended', ended_at = NOW() WHERE athlete_id = ? AND status = 'active'", [$id]);
                Db::run('UPDATE goal_participants SET left_at = NOW() WHERE athlete_id = ? AND left_at IS NULL', [$id]);
                // autorização de publicação retirada: conteúdos que citam o atleta saem do site
                Db::run('UPDATE consents SET revoked_at = NOW() WHERE user_id = ? AND type = ? AND revoked_at IS NULL', [$id, 'publicacao']);
                Db::run('DELETE FROM athlete_profiles WHERE user_id = ?', [$id]); // telefone, nascimento, emergência
                Db::run('DELETE FROM user_roles WHERE user_id = ?', [$id]);
                Db::run('DELETE FROM password_resets WHERE user_id = ?', [$id]);
                Db::run(
                    "UPDATE users SET status = 'blocked', deleted_at = NOW(), session_version = session_version + 1, must_change_password = 0,
                       name = ?, email = ?, password_hash = ? WHERE id = ?",
                    [mb_substr($athlete['name'], 0, 108) . ' (excluído)', "excluido-$id@conta.invalid", password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT), $id]
                );
                $pdo->commit();
            } catch (\Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
        Audit::log('athlete_deleted', (int) $me['id'], 'user', $id, ['history_kept' => !$erased]);
        Http::flash('ok', $erased ? 'Conta excluída.' : 'Conta excluída. Os treinos, contratos, pagamentos e pedidos antigos foram mantidos no histórico, e o e-mail ficou livre.');
        Http::redirect('/admin/atletas');
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
