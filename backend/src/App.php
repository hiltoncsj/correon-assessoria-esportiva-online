<?php
declare(strict_types=1);
namespace Correon;

/** Roteador e controladores da Sprint 1 (autenticação, cadastro e áreas por perfil). */
final class App
{
    private const MSG_LOGIN = 'E-mail ou senha incorretos, ou muitas tentativas. Tente novamente em alguns minutos.';

    public static function run(): void
    {
        $_GET = array_filter($_GET, 'is_string');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = rtrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/') ?: '/';
        if (str_starts_with($path, '/api/')) {
            Api::handle($method, $path); // público, sem sessão nem cookies
        }

        Http::secureHeaders();
        Http::startSession();

        try {
            if ($method === 'POST' && !Http::checkCsrf()) {
                View::render('errors/error', ['title' => 'Sessão expirada', 'code' => 419,
                    'msg' => 'Sua sessão expirou. Volte e tente novamente.'], 419);
            }
            self::dispatch($method, $path);
        } catch (\Throwable $e) {
            error_log('[app] ' . $e::class . ': ' . $e->getMessage());
            View::render('errors/error', ['title' => 'Erro', 'code' => 500,
                'msg' => 'Ocorreu um erro inesperado. Tente novamente mais tarde.'], 500);
        }
    }

    private static function dispatch(string $method, string $path): void
    {
        $routes = [
            'GET /entrar' => 'loginForm', 'POST /entrar' => 'login',
            'GET /cadastro' => 'registerForm', 'POST /cadastro' => 'register',
            'GET /trocar-senha' => 'firstPasswordForm', 'POST /trocar-senha' => 'firstPassword',
            'GET /recuperar-senha' => 'forgotForm', 'POST /recuperar-senha' => 'forgot',
            'POST /sair' => 'logout',
            'GET /app' => 'Athlete@dashboard', 'GET /app/perfil' => 'Athlete@profileForm', 'POST /app/perfil' => 'Athlete@profileSave',
            'GET /app/objetivos' => 'Goals@athleteList', 'POST /app/objetivos' => 'Goals@athleteCreate', 'GET /treinador/objetivos' => 'Goals@trainerUpcoming',
            'GET /app/senha' => 'Account@form', 'POST /app/senha' => 'Account@save',
            'GET /treinador/senha' => 'Account@form', 'POST /treinador/senha' => 'Account@save',
            'GET /admin/senha' => 'Account@form', 'POST /admin/senha' => 'Account@save',
            'GET /app/modalidades' => 'Athlete@modalities', 'GET /app/treinos' => 'Athlete@workouts',
            'GET /app/treinador' => 'Athlete@trainers', 'GET /app/assinaturas' => 'Athlete@billing', 'GET /app/personal' => 'PersonalPages@athlete', 'POST /app/personal' => 'PersonalPages@athleteRequest',
            'GET /treinador/personal' => 'PersonalPages@trainer', 'GET /admin/personal' => 'PersonalPages@adminList',
            'GET /treinador' => 'TrainerPanel@home', 'GET /treinador/atletas' => 'TrainerPanel@athletes',
            'GET /treinador/acompanhamento' => 'TrainerPanel@followUp', 'GET /treinador/agenda' => 'TrainerPanel@agenda',
            'GET /treinador/disponibilidade' => 'TrainerPanel@availability', 'POST /treinador/disponibilidade' => 'TrainerPanel@availabilityAdd',
            'GET /treinador/novidades' => 'TrainerPanel@news', 'GET /treinador/modelos' => 'Templates@index', 'GET /treinador/modelos/novo' => 'Templates@newForm',
            'POST /treinador/modelos/novo' => 'Templates@create', 'GET /treinador/modelos/aplicar' => 'Templates@applyForm', 'POST /treinador/modelos/montar' => 'Templates@plan', 'POST /treinador/modelos/excluir' => 'Templates@removeWeek',
            'GET /treinador/zonas' => 'Trainer@zonesAll', 'POST /treinador/zonas' => 'Trainer@zonesAllSave', 'POST /treinador/modelos/aplicar' => 'Templates@applySave',
            'GET /treinador/treinos/novo' => 'Trainer@newForm', 'POST /treinador/treinos/novo' => 'Trainer@create',
            'GET /admin' => 'Admin@home', 'GET /admin/conteudo' => 'ContentPages@index', 'GET /admin/conteudo/novo' => 'ContentPages@newForm',
            'POST /admin/conteudo' => 'ContentPages@create', 'GET /admin/comunicados' => 'ContentPages@announcements', 'POST /admin/comunicados' => 'ContentPages@announce',
            'GET /app/avisos' => 'ContentPages@notices', 'GET /app/privacidade' => 'ContentPages@privacy', 'POST /app/privacidade' => 'ContentPages@savePrivacy', 'GET /admin/precos' => 'Finance@prices', 'POST /admin/precos' => 'Finance@savePrices',
            'GET /admin/contratos' => 'Finance@contracts', 'GET /admin/financeiro' => 'Finance@overview', 'GET /admin/atletas' => 'Admin@athletes', 'POST /admin/atletas' => 'Admin@createAthlete', 'GET /admin/treinadores' => 'Admin@trainers',
            'POST /admin/treinadores' => 'Admin@createTrainer', 'GET /admin/relatorios' => 'Admin@reports', 'GET /admin/auditoria' => 'Admin@audit',
            'GET /termos' => 'terms', 'GET /privacidade' => 'privacy',
        ];
        if (preg_match('#^/redefinir-senha/([0-9a-f]{64})$#', $path, $m)) {
            $method === 'GET' ? self::resetForm($m[1]) : ($method === 'POST' ? self::reset($m[1]) : self::notFound());
            return;
        }
        // Rotas com id numérico: [método, padrão, Classe@método]
        foreach ([
            ['GET', '#^/app/objetivos/(\d+)$#', 'Goals@athleteEdit'],
            ['POST', '#^/app/objetivos/(\d+)$#', 'Goals@athleteUpdate'],
            ['GET', '#^/treinador/atletas/(\d+)/objetivos$#', 'Goals@trainerList'],
            ['POST', '#^/treinador/atletas/(\d+)/objetivos$#', 'Goals@trainerCreate'],
            ['GET', '#^/treinador/objetivos/(\d+)$#', 'Goals@trainerEdit'],
            ['POST', '#^/treinador/objetivos/(\d+)$#', 'Goals@trainerUpdate'],
            ['GET', '#^/app/treinos/(\d+)$#', 'Athlete@workout'],
            ['POST', '#^/app/treinos/(\d+)/registro$#', 'Athlete@logSave'],
            ['POST', '#^/treinador/disponibilidade/(\d+)/remover$#', 'TrainerPanel@availabilityRemove'],
            ['GET', '#^/admin/atletas/(\d+)/contrato$#', 'Finance@newContract'],
            ['POST', '#^/admin/atletas/(\d+)/contrato$#', 'Finance@createContract'],
            ['GET', '#^/admin/contratos/(\d+)$#', 'Finance@contract'],
            ['POST', '#^/admin/contratos/(\d+)/cancelar$#', 'Finance@cancel'],
            ['POST', '#^/admin/contratos/(\d+)/renovar$#', 'Finance@renew'],
            ['POST', '#^/admin/contratos/(\d+)/trocar$#', 'Finance@swap'],
            ['POST', '#^/admin/cobrancas/(\d+)/pagar$#', 'Finance@pay'],
            ['POST', '#^/admin/cobrancas/(\d+)/estornar$#', 'Finance@reverse'],
            ['POST', '#^/app/personal/(\d+)/desistir$#', 'PersonalPages@athleteCancel'],
            ['POST', '#^/treinador/personal/(\d+)$#', 'PersonalPages@trainerAction'],
            ['GET', '#^/admin/atletas/(\d+)/personal$#', 'PersonalPages@adminNew'],
            ['POST', '#^/admin/atletas/(\d+)/personal$#', 'PersonalPages@adminCreate'],
            ['GET', '#^/admin/personal/(\d+)$#', 'PersonalPages@adminPackage'],
            ['POST', '#^/admin/personal/(\d+)/cancelar$#', 'PersonalPages@adminCancel'],
            ['POST', '#^/admin/personal/(\d+)/pagar$#', 'PersonalPages@adminPay'],
            ['GET', '#^/admin/conteudo/(\d+)$#', 'ContentPages@edit'],
            ['POST', '#^/admin/conteudo/(\d+)$#', 'ContentPages@update'],
            ['GET', '#^/admin/imagem/(\d+)$#', 'ContentPages@adminImage'],
            ['GET', '#^/admin/atletas/(\d+)$#', 'Admin@athlete'],
            ['POST', '#^/admin/atletas/(\d+)/vinculos$#', 'Admin@assign'],
            ['POST', '#^/admin/atletas/(\d+)/vinculos/encerrar$#', 'Admin@unassign'],
            ['GET', '#^/admin/treinadores/(\d+)$#', 'Admin@trainer'],
            ['POST', '#^/admin/treinadores/(\d+)/capacidade$#', 'Admin@setCapacity'],
            ['POST', '#^/admin/treinadores/(\d+)/modalidades$#', 'Admin@setModalities'],
            ['POST', '#^/admin/usuarios/(\d+)/status$#', 'Admin@setStatus'],
            ['POST', '#^/admin/usuarios/(\d+)/senha$#', 'Admin@resetPassword'],
            ['POST', '#^/admin/usuarios/(\d+)/excluir$#', 'Admin@deleteTrainer'],
            ['GET', '#^/treinador/atletas/(\d+)$#', 'Trainer@athlete'],
            ['POST', '#^/treinador/atletas/(\d+)/zonas$#', 'Trainer@zonesSave'],
            ['POST', '#^/treinador/atletas/(\d+)/nivel$#', 'Trainer@levelSave'],
            ['GET', '#^/treinador/treinos/(\d+)$#', 'Trainer@edit'],
            ['POST', '#^/treinador/treinos/(\d+)$#', 'Trainer@update'],
            ['GET', '#^/treinador/modelos/(\d+)$#', 'Templates@edit'],
            ['POST', '#^/treinador/modelos/(\d+)$#', 'Templates@update'],
        ] as [$mt, $re, $target]) {
            if ($method === $mt && preg_match($re, $path, $m)) {
                [$cls, $fn] = explode('@', $target);
                $class = __NAMESPACE__ . '\\' . $cls;
                $class::$fn((int) $m[1]);
            }
        }
        $h = $routes["$method $path"] ?? null;
        if ($h === null) {
            self::notFound();
        }
        if (str_contains($h, '@')) {
            [$cls, $fn] = explode('@', $h);
            $class = __NAMESPACE__ . '\\' . $cls;
            $class::$fn();
        }
        self::$h();
    }

    public static function notFound(): never
    {
        View::render('errors/error', ['title' => 'Não encontrado', 'code' => 404, 'msg' => 'Página não encontrada.'], 404);
    }

    /** Exige login e perfil. Autorização sempre aqui, no servidor. */
    public static function require(string $role): array
    {
        $u = Auth::user();
        if ($u === null) {
            Http::redirect('/entrar');
        }
        if ((int) $u['must_change_password'] === 1) {
            Http::redirect('/trocar-senha'); // senha provisória: nada é acessível antes da troca
        }
        if (!in_array($role, $u['roles'], true)) {
            Audit::log('access_denied', (int) $u['id'], 'route', null, ['path' => parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), 'need' => $role]);
            View::render('errors/error', ['title' => 'Acesso negado', 'code' => 403,
                'msg' => 'Você não tem permissão para acessar esta área.'], 403);
        }
        return $u;
    }

    private static function guestOnly(): void
    {
        $u = Auth::user();
        if ($u !== null) {
            Http::redirect(Auth::homeFor($u['roles']));
        }
    }

    // ---- Login / logout --------------------------------------------------

    private static function loginForm(): never
    {
        self::guestOnly();
        View::render('auth/login', ['title' => 'Entrar', 'email' => '']);
    }

    private static function login(): never
    {
        self::guestOnly();
        $email = strtolower(Http::input('email'));
        $pass = (string) ($_POST['password'] ?? '');
        $ip = Http::ip();

        $blocked = RateLimiter::tooMany('login', $email, 5, 900)
            || RateLimiter::tooMany('login', $ip, 20, 900, true);
        $id = $blocked || $email === '' || $pass === '' ? null : Auth::verify($email, $pass);

        if ($id === null) {
            RateLimiter::hit('login', $email);
            Audit::log($blocked ? 'login_blocked' : 'login_failed', null, 'user', null, ['email' => substr($email, 0, 190)]);
            View::render('auth/login', ['title' => 'Entrar', 'email' => $email, 'error' => self::MSG_LOGIN], 401);
        }
        RateLimiter::clear('login', $email);
        Auth::login($id);
        Audit::log('login_success', $id, 'user', $id);
        $me = Auth::user();
        Http::redirect((int) ($me['must_change_password'] ?? 0) === 1 ? '/trocar-senha' : Auth::homeFor($me['roles'] ?? []));
    }

    private static function logout(): never
    {
        $u = Auth::user();
        if ($u) {
            Audit::log('logout', (int) $u['id'], 'user', (int) $u['id']);
        }
        Auth::logout();
        Http::startSession();
        Http::flash('ok', 'Você saiu da sua conta.');
        Http::redirect('/entrar');
    }

    // ---- Troca obrigatória da senha provisória --------------------------

    private static function pendingUser(): array
    {
        $u = Auth::user();
        if ($u === null) {
            Http::redirect('/entrar');
        }
        if ((int) $u['must_change_password'] !== 1) {
            Http::redirect(Auth::homeFor($u['roles']));
        }
        return $u;
    }

    private static function firstPasswordForm(): never
    {
        $u = self::pendingUser();
        View::render('auth/first_password', ['title' => 'Crie a sua senha', 'error' => null, 'needsTerms' => Auth::needsTerms((int) $u['id'])]);
    }

    private static function firstPassword(): never
    {
        $u = self::pendingUser();
        $id = (int) $u['id'];
        $new = (string) ($_POST['password'] ?? '');
        $err = Auth::passwordError($new) ?? ($new !== (string) ($_POST['password2'] ?? '') ? 'As senhas não conferem.' : null);
        $hash = Db::one('SELECT password_hash FROM users WHERE id = ?', [$id])['password_hash'];
        if ($err === null && password_verify($new, $hash)) {
            $err = 'Escolha uma senha diferente da provisória.';
        }
        $needsTerms = Auth::needsTerms($id);
        if ($err === null && $needsTerms && Http::input('accept') !== '1') {
            $err = 'É necessário aceitar os Termos de Uso e a Política de Privacidade.';
        }
        if ($err !== null) {
            View::render('auth/first_password', ['title' => 'Crie a sua senha', 'error' => $err, 'needsTerms' => $needsTerms], 422);
        }
        if ($needsTerms) {
            Auth::acceptTerms($id);
        }
        Db::run('UPDATE users SET password_hash = ?, must_change_password = 0, session_version = session_version + 1 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $id]);
        Auth::login($id);
        Audit::log('password_first_set', $id, 'user', $id);
        Http::flash('ok', 'Senha definida. Bem-vindo à CorreON!');
        Http::redirect(Auth::homeFor($u['roles']));
    }

    // ---- Cadastro --------------------------------------------------------

    private static function registerForm(): never
    {
        self::guestOnly();
        View::render('auth/register', ['title' => 'Criar conta', 'old' => ['name' => '', 'email' => ''], 'errors' => []]);
    }

    private static function register(): never
    {
        self::guestOnly();
        $name = preg_replace('/\s+/u', ' ', Http::input('name')) ?? '';
        $email = strtolower(Http::input('email'));
        $pass = (string) ($_POST['password'] ?? '');
        $pass2 = (string) ($_POST['password2'] ?? '');
        $old = ['name' => $name, 'email' => $email];
        $errors = [];

        if (RateLimiter::tooMany('register', Http::ip(), 10, 3600, true)) {
            View::render('auth/register', ['title' => 'Criar conta', 'old' => $old,
                'errors' => ['form' => 'Muitas tentativas. Tente novamente mais tarde.']], 429);
        }
        RateLimiter::hit('register', Http::ip());

        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $errors['name'] = 'Informe seu nome (2 a 120 caracteres).';
        }
        if (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Informe um e-mail válido.';
        }
        if (($pe = Auth::passwordError($pass)) !== null) {
            $errors['password'] = $pe;
        } elseif ($pass !== $pass2) {
            $errors['password2'] = 'As senhas não conferem.';
        }
        if (Http::input('accept') !== '1') {
            $errors['accept'] = 'É necessário aceitar os Termos de Uso e a Política de Privacidade.';
        }

        if (!$errors) {
            try {
                // Cadastro público cria SEMPRE atleta; nunca aceita perfil vindo do formulário.
                $id = Auth::createUser($name, $email, $pass, 'athlete', true);
                Auth::login($id);
                Audit::log('register', $id, 'user', $id);
                Http::flash('ok', 'Conta criada com sucesso. Bem-vindo à CorreON!');
                Http::redirect('/app');
            } catch (\PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                Audit::log('register_duplicate', null, 'user', null, ['email' => $email]);
                $errors['form'] = 'Não foi possível concluir o cadastro com esses dados. Se você já tem conta, entre ou recupere a senha.';
            }
        }
        View::render('auth/register', ['title' => 'Criar conta', 'old' => $old, 'errors' => $errors], 422);
    }

    // ---- Recuperação de senha -------------------------------------------

    private static function forgotForm(): never
    {
        self::guestOnly();
        View::render('auth/forgot', ['title' => 'Recuperar senha']);
    }

    private static function forgot(): never
    {
        self::guestOnly();
        $email = strtolower(Http::input('email'));
        $generic = 'Se o e-mail estiver cadastrado, enviaremos as instruções para redefinir a senha.';

        if (RateLimiter::tooMany('reset', Http::ip(), 5, 3600, true)) {
            View::render('auth/forgot', ['title' => 'Recuperar senha', 'error' => 'Muitas solicitações. Tente novamente mais tarde.'], 429);
        }
        RateLimiter::hit('reset', $email);

        $user = $email !== '' ? Db::one("SELECT id, name, email FROM users WHERE email = ? AND status = 'active'", [$email]) : null;
        if ($user) {
            $token = bin2hex(random_bytes(32));
            Db::run('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?,?, NOW() + INTERVAL 1 HOUR)',
                [$user['id'], hash('sha256', $token)]);
            $url = rtrim((string) Env::get('APP_URL', ''), '/') . '/redefinir-senha/' . $token;
            Mailer::send($user['email'], 'CorreON — redefinição de senha',
                "Olá, {$user['name']}.\n\nPara criar uma nova senha, acesse o link abaixo (válido por 1 hora, uso único):\n$url\n\n"
                . "Se você não pediu isso, ignore este e-mail.");
            Audit::log('password_reset_requested', (int) $user['id'], 'user', (int) $user['id']);
        }
        View::render('auth/forgot', ['title' => 'Recuperar senha', 'sent' => $generic]);
    }

    /** @return array<string,mixed>|null */
    private static function findReset(string $token): ?array
    {
        return Db::one('SELECT id, user_id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()',
            [hash('sha256', $token)]);
    }

    private static function resetForm(string $token): never
    {
        self::guestOnly();
        if (!self::findReset($token)) {
            View::render('auth/reset_invalid', ['title' => 'Link inválido'], 410);
        }
        View::render('auth/reset', ['title' => 'Nova senha', 'token' => $token, 'error' => null]);
    }

    private static function reset(string $token): never
    {
        self::guestOnly();
        $row = self::findReset($token);
        if (!$row) {
            View::render('auth/reset_invalid', ['title' => 'Link inválido'], 410);
        }
        $pass = (string) ($_POST['password'] ?? '');
        $err = Auth::passwordError($pass) ?? ($pass !== (string) ($_POST['password2'] ?? '') ? 'As senhas não conferem.' : null);
        if ($err !== null) {
            View::render('auth/reset', ['title' => 'Nova senha', 'token' => $token, 'error' => $err], 422);
        }
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            // UPDATE condicional garante uso único mesmo com requisições simultâneas.
            $st = Db::run('UPDATE password_resets SET used_at = NOW() WHERE id = ? AND used_at IS NULL', [$row['id']]);
            if ($st->rowCount() !== 1) {
                $pdo->rollBack();
                View::render('auth/reset_invalid', ['title' => 'Link inválido'], 410);
            }
            Db::run('UPDATE users SET password_hash = ?, must_change_password = ?, session_version = session_version + 1 WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), Auth::needsTerms((int) $row['user_id']) ? 1 : 0, $row['user_id']]);
            Db::run('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$row['user_id']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        Audit::log('password_reset_done', (int) $row['user_id'], 'user', (int) $row['user_id']);
        Http::flash('ok', 'Senha alterada. Entre com a nova senha.');
        Http::redirect('/entrar');
    }

    // ---- Áreas por perfil (esqueleto da Sprint 1; conteúdo real nas próximas sprints) ----

    // ---- Páginas legais (textos ainda não aprovados) --------------------

    private static function terms(): never
    {
        View::render('legal/pending', ['title' => 'Termos de Uso', 'heading' => 'Termos de Uso']);
    }

    private static function privacy(): never
    {
        View::render('legal/pending', ['title' => 'Política de Privacidade', 'heading' => 'Política de Privacidade']);
    }
}
