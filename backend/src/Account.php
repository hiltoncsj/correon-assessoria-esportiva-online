<?php
declare(strict_types=1);
namespace Correon;

/**
 * Troca da própria senha, para atleta (/app/senha), treinador (/treinador/senha) e admin (/admin/senha).
 * Sempre sobre o usuário da sessão: nenhuma rota recebe id.
 */
final class Account
{
    private const AREAS = [
        '/app' => ['role' => 'athlete', 'shell' => 'athlete', 'label' => 'Atleta'],
        '/treinador' => ['role' => 'trainer', 'shell' => 'trainer', 'label' => 'Treinador'],
        '/admin' => ['role' => 'admin', 'shell' => 'admin', 'label' => 'Administrador'],
    ];

    /** Área a partir do endereço; o perfil é exigido no servidor. @return array{0:array<string,mixed>,1:array<string,string>,2:string} */
    private static function context(): array
    {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $base = '/' . (explode('/', trim($path, '/'))[0] ?? '');
        $area = self::AREAS[$base] ?? null;
        if ($area === null) {
            App::notFound();
        }
        return [App::require($area['role']), $area, $base . '/senha'];
    }

    /** @param array<string,string> $area */
    private static function render(array $area, string $action, ?string $error, int $status): never
    {
        View::render('account/password', ['title' => 'Alterar senha', 'shell' => $area['shell'], 'active' => 'senha',
            'label' => $area['label'], 'action' => $action, 'error' => $error], $status);
    }

    public static function form(): never
    {
        [, $area, $action] = self::context();
        self::render($area, $action, null, 200);
    }

    public static function save(): never
    {
        [$u, $area, $action] = self::context();
        $id = (int) $u['id'];

        if (RateLimiter::tooMany('pwchange', (string) $id, 5, 900)) {
            self::render($area, $action, 'Muitas tentativas. Aguarde alguns minutos.', 429);
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
            self::render($area, $action, $err, 422);
        }
        RateLimiter::hit('pwchange', (string) $id);
        Db::run('UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $id]);
        Auth::login($id); // nova sessão com a versão atual; as outras sessões deixam de valer
        Audit::log('password_changed', $id, 'user', $id);
        Mailer::send((string) $u['email'], 'CorreON — sua senha foi alterada',
            "Olá, {$u['name']}.\n\nA senha da sua conta CorreON foi alterada em " . date('d/m/Y H:i') . ".\n"
            . 'Se não foi você, recupere o acesso em ' . rtrim((string) Env::get('APP_URL', ''), '/') . '/recuperar-senha e avise a CorreON.');
        Http::flash('ok', 'Senha alterada. Os outros dispositivos foram desconectados.');
        Http::redirect($action);
    }
}
