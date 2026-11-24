<?php
declare(strict_types=1);
namespace Correon;

final class Auth
{
    public const PASSWORD_MIN = 10;
    public const PASSWORD_MAX = 72; // limite do bcrypt (bytes)
    public const CONSENT_VERSION = 'rascunho-0'; // trocar quando os textos jurídicos forem aprovados

    /** @var array<string,mixed>|null|false */
    private static $cached = false;

    /** Usuário logado (recarregado do banco a cada requisição) ou null. @return array<string,mixed>|null */
    public static function user(): ?array
    {
        if (self::$cached !== false) {
            return self::$cached;
        }
        $id = $_SESSION['uid'] ?? null;
        $user = null;
        if (is_int($id)) {
            $row = Db::one("SELECT id, name, email, status FROM users WHERE id = ?", [$id]);
            if ($row && $row['status'] === 'active') {
                $row['roles'] = array_column(
                    Db::all('SELECT r.slug FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?', [$id]),
                    'slug'
                );
                $user = $row;
            } else {
                unset($_SESSION['uid']);
            }
        }
        return self::$cached = $user;
    }

    public static function hasRole(string $role): bool
    {
        $u = self::user();
        return $u !== null && in_array($role, $u['roles'], true);
    }

    /** Rota inicial conforme o perfil de maior privilégio. */
    public static function homeFor(array $roles): string
    {
        return match (true) {
            in_array('admin', $roles, true) => '/admin',
            in_array('trainer', $roles, true) => '/treinador',
            default => '/app',
        };
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = $userId;
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        self::$cached = false;
        Db::run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$userId]);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'],
                'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
        }
        session_destroy();
        self::$cached = false;
    }

    /** Valida credenciais sem revelar se o e-mail existe (tempo similar). @return int|null id do usuário */
    public static function verify(string $email, string $password): ?int
    {
        $row = Db::one('SELECT id, password_hash, status FROM users WHERE email = ?', [$email]);
        $hash = $row['password_hash'] ?? '$2y$12$usesomesillystringforsaleeOdummyhashdummyhashdummyhashdum';
        $ok = password_verify($password, $hash);
        if (!$row || !$ok || $row['status'] !== 'active') {
            return null;
        }
        if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
            Db::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $row['id']]);
        }
        return (int) $row['id'];
    }

    /** @return string|null mensagem de erro, ou null se a senha é válida */
    public static function passwordError(string $password): ?string
    {
        $len = strlen($password);
        if (mb_strlen($password) < self::PASSWORD_MIN) {
            return 'A senha deve ter pelo menos ' . self::PASSWORD_MIN . ' caracteres.';
        }
        if ($len > self::PASSWORD_MAX) {
            return 'A senha deve ter no máximo ' . self::PASSWORD_MAX . ' bytes.';
        }
        return null;
    }

    /**
     * Cria um usuário com perfil. Cadastro público usa sempre 'athlete'.
     * @throws \PDOException e-mail duplicado (SQLSTATE 23000)
     */
    public static function createUser(string $name, string $email, string $password, string $role, bool $withConsent): int
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            Db::run('INSERT INTO users (name, email, password_hash) VALUES (?,?,?)',
                [$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $id = (int) $pdo->lastInsertId();
            Db::run('INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE slug = ?', [$id, $role]);
            if ($withConsent) {
                foreach (['termos', 'privacidade'] as $type) {
                    Db::run('INSERT INTO consents (user_id, type, version, ip) VALUES (?,?,?,?)',
                        [$id, $type, self::CONSENT_VERSION, Http::ip()]);
                }
            }
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
