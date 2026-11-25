<?php
declare(strict_types=1);
namespace Correon;

/** Roteador e controladores da Sprint 1 (autenticação, cadastro e áreas por perfil). */
final class App
{
    private const MSG_LOGIN = 'E-mail ou senha incorretos, ou muitas tentativas. Tente novamente em alguns minutos.';

    public static function run(): void
    {
        Http::secureHeaders();
        Http::startSession();

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = rtrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/') ?: '/';

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
            'GET /recuperar-senha' => 'forgotForm', 'POST /recuperar-senha' => 'forgot',
            'POST /sair' => 'logout',
            'GET /app' => 'Athlete@dashboard', 'GET /app/perfil' => 'Athlete@profileForm', 'POST /app/perfil' => 'Athlete@profileSave',
            'GET /app/senha' => 'Athlete@passwordForm', 'POST /app/senha' => 'Athlete@passwordSave',
            'GET /app/modalidades' => 'soon:modalidades', 'GET /app/treinos' => 'Athlete@workouts',
            'GET /app/treinador' => 'Athlete@trainers', 'GET /app/assinaturas' => 'soon:assinaturas',
            'GET /treinador' => 'Trainer@home', 'GET /treinador/treinos/novo' => 'Trainer@newForm', 'POST /treinador/treinos/novo' => 'Trainer@create',
            'GET /admin' => 'adminHome',
            'GET /termos' => 'terms', 'GET /privacidade' => 'privacy',
        ];
        if (preg_match('#^/redefinir-senha/([0-9a-f]{64})$#', $path, $m)) {
            $method === 'GET' ? self::resetForm($m[1]) : ($method === 'POST' ? self::reset($m[1]) : self::notFound());
            return;
        }
        // Rotas com id numérico: [método, padrão, Classe@método]
        foreach ([
            ['GET', '#^/app/treinos/(\d+)$#', 'Athlete@workout'],
            ['POST', '#^/app/treinos/(\d+)/registro$#', 'Athlete@logSave'],
            ['GET', '#^/treinador/atletas/(\d+)$#', 'Trainer@athlete'],
            ['GET', '#^/treinador/treinos/(\d+)$#', 'Trainer@edit'],
            ['POST', '#^/treinador/treinos/(\d+)$#', 'Trainer@update'],
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
        if (str_starts_with($h, 'soon:')) {
            Athlete::soon(substr($h, 5));
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
        Http::redirect(Auth::homeFor(Auth::user()['roles'] ?? []));
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
            Db::run('UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $row['user_id']]);
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

    private static function adminHome(): never
    {
        $u = self::require('admin');
        $counts = Db::all('SELECT r.name, COUNT(ur.user_id) total FROM roles r LEFT JOIN user_roles ur ON ur.role_id = r.id GROUP BY r.id, r.name ORDER BY r.id');
        View::render('dash/area', ['title' => 'Administração', 'heading' => 'Painel administrativo', 'role' => 'Administrador', 'u' => $u,
            'counts' => $counts, 'note' => 'Gestão de atletas, treinadores, planos e contratos será liberada nas próximas etapas.']);
    }

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
