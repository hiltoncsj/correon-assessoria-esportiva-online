<?php
declare(strict_types=1);
/*
 * Testes de integração HTTP da Sprint 1.
 * Uso: php backend/tests/run.php   (usa o banco *_test; apaga os dados dele!)
 * Sobe o servidor embutido do PHP na porta 8099 com DB_NAME=correon_test e MAIL_DRIVER=log.
 */
putenv('DB_NAME=correon_test');
putenv('MAIL_DRIVER=log');
putenv('APP_ENV=local');
putenv('APP_URL=http://localhost:8099');
require dirname(__DIR__) . '/src/bootstrap.php';

use Correon\Db;

if (!str_ends_with((string) Correon\Env::get('DB_NAME'), '_test')) {
    exit("Recusado: DB_NAME precisa terminar em _test.\n");
}

$php = PHP_BINARY;
$pdo = Db::pdo();
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach (['athlete_profiles', 'audit_log', 'consents', 'password_resets', 'rate_events', 'user_roles', 'users', 'schema_migrations'] as $t) {
    $pdo->exec("DROP TABLE IF EXISTS $t"); // somente no banco de teste
}
$pdo->exec('DROP TABLE IF EXISTS roles');
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
passthru(escapeshellarg($php) . ' ' . escapeshellarg(__DIR__ . '/../bin/migrate.php') . ' > ' . (PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null'));
array_map('unlink', glob(BASE_PATH . '/storage/mail/*.txt') ?: []);

$proc = proc_open([$php, '-S', '127.0.0.1:8099', '-t', dirname(__DIR__) . '/public', dirname(__DIR__) . '/public/router.php'],
    [1 => ['file', sys_get_temp_dir() . '/correon-srv.log', 'w'], 2 => ['file', sys_get_temp_dir() . '/correon-srv.log', 'a']], $pipes);
register_shutdown_function(static function () use ($proc) { proc_terminate($proc); });
usleep(800000);

$pass = 0; $fail = 0;
function check(string $name, bool $ok, string $extra = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? '  ok   ' : '  FALHA ') . $name . ($ok ? '' : "  $extra") . "\n";
}

final class Client
{
    public string $jar;
    public array $lastHeaders = [];
    public function __construct() { $this->jar = tempnam(sys_get_temp_dir(), 'cj'); }
    /** @return array{0:int,1:string} status, body */
    public function req(string $method, string $path, array $data = []): array
    {
        $ch = curl_init('http://127.0.0.1:8099' . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 8,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_CUSTOMREQUEST => $method]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        $raw = (string) curl_exec($ch);
        if (curl_errno($ch)) { echo "  [curl] $method $path: " . curl_error($ch) . "
"; }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $this->lastHeaders = explode("\r\n", substr($raw, 0, $hs));
        return [$status, substr($raw, $hs)];
    }
    public function header(string $name): ?string
    {
        foreach ($this->lastHeaders as $h) {
            if (stripos($h, $name . ':') === 0) { return trim(substr($h, strlen($name) + 1)); }
        }
        return null;
    }
    public function csrf(string $path): string
    {
        [, $b] = $this->req('GET', $path);
        preg_match('/name="_csrf" value="([0-9a-f]{64})"/', $b, $m);
        return $m[1] ?? '';
    }
    public function post(string $path, array $data, ?string $formPath = null): array
    {
        $data['_csrf'] = $this->csrf($formPath ?? $path);
        return $this->req('POST', $path, $data);
    }
}

function register(Client $c, array $o = []): array
{
    return $c->post('/cadastro', $o + ['name' => 'Maria Teste', 'email' => 'maria@example.com',
        'password' => 'senha-forte-123', 'password2' => 'senha-forte-123', 'accept' => '1']);
}

echo "Público e cabeçalhos\n";
$a = new Client();
[$s, $b] = $a->req('GET', '/entrar');
check('GET /entrar = 200', $s === 200);
check('CSP presente', str_contains((string) $a->header('Content-Security-Policy'), "default-src 'self'"));
check('X-Frame-Options DENY', $a->header('X-Frame-Options') === 'DENY');
check('cookie de sessão HttpOnly + SameSite', (bool) preg_grep('/^Set-Cookie: correon_sid=.*HttpOnly.*SameSite=Lax/i', $a->lastHeaders));
[$s] = $a->req('GET', '/app');
check('/app sem login redireciona a /entrar', $s === 303 && $a->header('Location') === '/entrar');
[$s] = $a->req('GET', '/inexistente-xyz');
check('rota desconhecida = 404', $s === 404);

echo "CSRF\n";
[$s] = $a->req('POST', '/entrar', ['email' => 'a@b.com', 'password' => 'x']);
check('POST sem token CSRF = 419', $s === 419);

echo "Cadastro\n";
[$s] = register($a, ['password' => 'curta', 'password2' => 'curta']);
check('senha curta rejeitada (422)', $s === 422);
[$s] = register($a, ['accept' => '']);
check('sem aceite dos termos rejeitado (422)', $s === 422);
[$s] = register($a, ['email' => 'nao-e-email']);
check('e-mail inválido rejeitado (422)', $s === 422);
[$s] = register($a, ['password2' => 'diferente-123456']);
check('senhas diferentes rejeitadas (422)', $s === 422);
$sidAntes = (string) preg_replace('/.*correon_sid\s+/s', '', (string) file_get_contents($a->jar));
[$s] = register($a, ['name' => '<script>alert(1)</script>', 'role' => 'admin', 'roles' => ['admin']]);
check('cadastro válido redireciona a /app', $s === 303 && $a->header('Location') === '/app', "status=$s");
$sidDepois = (string) preg_replace('/.*correon_sid\s+/s', '', (string) file_get_contents($a->jar));
check('ID de sessão regenerado no login', $sidAntes !== $sidDepois);
$role = Db::one("SELECT GROUP_CONCAT(r.slug) s FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE u.email='maria@example.com'");
check('perfil criado é só atleta (ignora "role=admin" do formulário)', ($role['s'] ?? '') === 'athlete', (string) ($role['s'] ?? ''));
[$s, $b] = $a->req('GET', '/app');
check('/app autenticado = 200', $s === 200);
check('nome com HTML é escapado (XSS)', !str_contains($b, '<script>alert(1)</script>') && str_contains($b, '&lt;script&gt;'));
$hash = Db::one("SELECT password_hash FROM users WHERE email='maria@example.com'")['password_hash'] ?? '';
check('senha armazenada com hash', str_starts_with($hash, '$2y$') || str_starts_with($hash, '$argon'));
check('consentimentos registrados (termos e privacidade)', (int) Db::one('SELECT COUNT(*) c FROM consents')['c'] === 2);

echo "Autorização por perfil (no servidor)\n";
[$s] = $a->req('GET', '/admin');
check('atleta em /admin = 403', $s === 403);
[$s] = $a->req('GET', '/treinador');
check('atleta em /treinador = 403', $s === 403);
check('acesso negado auditado', (int) Db::one("SELECT COUNT(*) c FROM audit_log WHERE action='access_denied'")['c'] >= 2);
[$s] = $a->req('GET', '/entrar');
check('logado em /entrar vai para a área do perfil', $s === 303 && $a->header('Location') === '/app');

echo "Cadastro duplicado\n";
$d = new Client();
[$s, $b] = register($d);
check('e-mail duplicado = 422 com mensagem genérica', $s === 422 && str_contains($b, 'Não foi possível concluir o cadastro'));

echo "Logout\n";
[$s] = $a->post('/sair', [], '/app');
check('logout redireciona', $s === 303);
[$s] = $a->req('GET', '/app');
check('após logout /app exige login', $s === 303 && $a->header('Location') === '/entrar');

echo "Login e limite de tentativas\n";
$l = new Client();
[$s] = $l->post('/entrar', ['email' => 'maria@example.com', 'password' => 'errada-123456']);
check('senha errada = 401', $s === 401);
[$s, $b] = $l->post('/entrar', ['email' => "x' OR '1'='1", 'password' => 'x']);
check('tentativa de SQL injection = 401 (sem erro 500)', $s === 401);
for ($i = 0; $i < 4; $i++) { $l->post('/entrar', ['email' => 'maria@example.com', 'password' => 'errada-123456']); }
[$s] = $l->post('/entrar', ['email' => 'maria@example.com', 'password' => 'senha-forte-123']);
check('após 5 falhas, a senha CERTA também é bloqueada', $s === 401);
Db::run('DELETE FROM rate_events');
[$s] = $l->post('/entrar', ['email' => 'maria@example.com', 'password' => 'senha-forte-123']);
check('sem bloqueio, login correto vai para /app', $s === 303 && $l->header('Location') === '/app');
check('falhas e sucesso auditados', (int) Db::one("SELECT COUNT(*) c FROM audit_log WHERE action IN ('login_failed','login_blocked','login_success')")['c'] >= 6);

echo "Admin e treinador (criados por CLI)\n";
foreach (['admin' => 'adm@example.com', 'trainer' => 'trn@example.com'] as $r => $em) {
    putenv('CORREON_NEW_PASSWORD=senha-staff-12345');
    exec(escapeshellarg($php) . ' ' . escapeshellarg(__DIR__ . '/../bin/create-user.php') . " $r " . escapeshellarg("Staff $r") . " $em", $out, $code);
    check("CLI cria $r", $code === 0);
}
Db::run('DELETE FROM rate_events');
$adm = new Client();
[$s] = $adm->post('/entrar', ['email' => 'adm@example.com', 'password' => 'senha-staff-12345']);
check('admin entra e vai para /admin', $s === 303 && $adm->header('Location') === '/admin');
[$s, $b] = $adm->req('GET', '/admin');
check('/admin = 200 com contagem real de usuários', $s === 200 && str_contains($b, 'Atleta: <strong>1</strong>'));
[$s] = $adm->req('GET', '/app');
check('admin em /app = 403 (perfis não se misturam)', $s === 403);
$trn = new Client();
[$s] = $trn->post('/entrar', ['email' => 'trn@example.com', 'password' => 'senha-staff-12345']);
check('treinador vai para /treinador', $s === 303 && $trn->header('Location') === '/treinador');
[$s] = $trn->req('GET', '/admin');
check('treinador em /admin = 403', $s === 403);

echo "Recuperação de senha\n";
$r = new Client();
[$s, $b] = $r->post('/recuperar-senha', ['email' => 'inexistente@example.com']);
check('e-mail inexistente: mesma resposta, sem e-mail enviado', $s === 200 && str_contains($b, 'Se o e-mail estiver cadastrado') && !glob(BASE_PATH . '/storage/mail/*.txt'));
[$s, $b] = $r->post('/recuperar-senha', ['email' => 'maria@example.com']);
check('e-mail existente: mesma resposta', $s === 200 && str_contains($b, 'Se o e-mail estiver cadastrado'));
$mails = glob(BASE_PATH . '/storage/mail/*.txt') ?: [];
check('e-mail de redefinição gerado', count($mails) === 1);
preg_match('#/redefinir-senha/([0-9a-f]{64})#', (string) file_get_contents($mails[0] ?? ''), $m);
$token = $m[1] ?? '';
check('token no banco só como hash', $token !== '' && Db::one('SELECT id FROM password_resets WHERE token_hash = ?', [$token]) === null
    && Db::one('SELECT id FROM password_resets WHERE token_hash = ?', [hash('sha256', $token)]) !== null);
[$s] = $r->req('GET', "/redefinir-senha/$token");
check('link válido = 200', $s === 200);
[$s] = $r->req('GET', '/redefinir-senha/' . str_repeat('a', 64));
check('token inventado = 410', $s === 410);
[$s] = $r->post("/redefinir-senha/$token", ['password' => 'nova-senha-9999', 'password2' => 'outra-coisa-99'], "/redefinir-senha/$token");
check('senhas diferentes na redefinição = 422', $s === 422);
[$s] = $r->post("/redefinir-senha/$token", ['password' => 'nova-senha-9999', 'password2' => 'nova-senha-9999'], "/redefinir-senha/$token");
check('redefinição concluída', $s === 303 && $r->header('Location') === '/entrar');
[$s] = $r->req('GET', "/redefinir-senha/$token");
check('token não pode ser reutilizado (410)', $s === 410);
Db::run('DELETE FROM rate_events');
[$s] = $r->post('/entrar', ['email' => 'maria@example.com', 'password' => 'senha-forte-123']);
check('senha antiga deixa de funcionar', $s === 401);
[$s] = $r->post('/entrar', ['email' => 'maria@example.com', 'password' => 'nova-senha-9999']);
check('nova senha funciona', $s === 303);
// expiração
$r2 = new Client();
Db::run('DELETE FROM rate_events');
$r2->post('/recuperar-senha', ['email' => 'maria@example.com']);
$mails = glob(BASE_PATH . '/storage/mail/*.txt') ?: [];
rsort($mails);
preg_match('#/redefinir-senha/([0-9a-f]{64})#', (string) file_get_contents($mails[0]), $m2);
Db::run('UPDATE password_resets SET expires_at = NOW() - INTERVAL 1 MINUTE');
[$s] = $r2->req('GET', '/redefinir-senha/' . ($m2[1] ?? ''));
check('token expirado = 410', $s === 410);

echo "Sprint 2 — área do atleta\n";
Db::run('DELETE FROM rate_events');
$m1 = new Client();
$m1->post('/entrar', ['email' => 'maria@example.com', 'password' => 'nova-senha-9999']);
[$s, $b] = $m1->req('GET', '/app');
check('dashboard do atleta = 200', $s === 200 && str_contains($b, 'Minha conta') && str_contains($b, 'Nenhum treino programado'));
check('dashboard sem métricas ou valores inventados', !preg_match('/Treinos realizados|R\$ ?\d/', $b));
foreach (['/app/perfil', '/app/modalidades', '/app/treinos', '/app/treinador', '/app/assinaturas', '/app/senha'] as $path) {
    [$s] = $m1->req('GET', $path);
    check("GET $path = 200", $s === 200, "status=$s");
}
[$s] = $trn->req('GET', '/app/perfil');
check('treinador em /app/perfil = 403', $s === 403);
[$s] = $adm->req('GET', '/app/senha');
check('admin em /app/senha = 403', $s === 403);
$anon = new Client();
[$s] = $anon->req('GET', '/app/perfil');
check('/app/perfil sem login redireciona', $s === 303 && $anon->header('Location') === '/entrar');
[$s] = $anon->req('POST', '/app/perfil', ['name' => 'x']);
check('POST /app/perfil sem login nem CSRF = 419', $s === 419);

$base = ['name' => 'Maria Teste', 'phone' => '(11) 91234-5678', 'birth_date' => '1990-05-20', 'city' => 'Santos', 'state' => 'SP',
    'goal' => 'Correr 10 km <b>sem parar</b>', 'sports_background' => '', 'emergency_name' => '', 'emergency_phone' => ''];
foreach ([
    'telefone inválido' => ['phone' => '123'],
    'nascimento no futuro' => ['birth_date' => date('Y-m-d', strtotime('+1 day'))],
    'menor de 13 anos' => ['birth_date' => date('Y-m-d', strtotime('-10 years'))],
    'data inexistente' => ['birth_date' => '2020-02-31'],
    'estado inválido' => ['state' => 'ZZ'],
    'contato de emergência incompleto' => ['emergency_name' => 'João'],
    'objetivo longo demais' => ['goal' => str_repeat('a', 501)],
] as $label => $over) {
    [$s] = $m1->post('/app/perfil', $over + $base);
    check("perfil rejeita: $label (422)", $s === 422, "status=$s");
}
[$s] = $m1->post('/app/perfil', $base);
check('perfil válido salva e redireciona', $s === 303 && $m1->header('Location') === '/app/perfil');
$row = Db::one("SELECT p.phone, p.city, p.state, p.birth_date FROM athlete_profiles p JOIN users u ON u.id=p.user_id WHERE u.email='maria@example.com'");
check('dados gravados (telefone só dígitos)', ($row['phone'] ?? '') === '11912345678' && ($row['city'] ?? '') === 'Santos' && ($row['birth_date'] ?? '') === '1990-05-20');
[$s, $b] = $m1->req('GET', '/app/perfil');
check('objetivo com HTML é escapado (XSS)', !str_contains($b, '<b>sem parar</b>') && str_contains($b, '&lt;b&gt;sem parar'));
[$s, $b] = $m1->req('GET', '/app');
check('completude real no dashboard (5 de 8 = 63%)', str_contains($b, '63% preenchido'));
check('auditoria do perfil não grava valores pessoais', !Db::one("SELECT id FROM audit_log WHERE action='profile_updated' AND (details LIKE '%Santos%' OR details LIKE '%91234%')"));

Db::run('DELETE FROM rate_events');
$other = new Client();
register($other, ['email' => 'joao@example.com', 'name' => 'João Outro']);
[$s, $b] = $other->req('GET', '/app/perfil');
check('outro atleta acessa o próprio perfil (200)', $s === 200, "status=$s");
check('outro atleta não vê dados de Maria', !str_contains($b, 'value="Santos"') && !str_contains($b, '11912345678') && !str_contains($b, 'sem parar'));

echo "Alterar senha e invalidação de sessões\n";
$ses2 = new Client();
Db::run('DELETE FROM rate_events');
$ses2->post('/entrar', ['email' => 'maria@example.com', 'password' => 'nova-senha-9999']);
[$s] = $ses2->req('GET', '/app');
check('segunda sessão ativa antes da troca', $s === 200);
[$s, $b] = $m1->req('GET', '/app/senha');
check('formulário de troca não pede a senha atual', $s === 200 && !str_contains($b, 'name="current"'));
[$s] = $m1->post('/app/senha', ['password' => 'curta', 'password2' => 'curta']);
check('nova senha fraca = 422', $s === 422);
[$s] = $m1->post('/app/senha', ['password' => 'nova-senha-9999', 'password2' => 'nova-senha-9999']);
check('nova senha igual à atual = 422', $s === 422);
$mailsAntes = count(glob(BASE_PATH . '/storage/mail/*.txt') ?: []);
[$s] = $m1->post('/app/senha', ['password' => 'outra-senha-1234', 'password2' => 'outra-senha-1234']);
check('troca de senha sem digitar a atual', $s === 303);
check('aviso de alteração enviado por e-mail', count(glob(BASE_PATH . '/storage/mail/*.txt') ?: []) === $mailsAntes + 1);
[$s] = $m1->req('GET', '/app');
check('sessão que trocou a senha continua válida', $s === 200);
[$s] = $ses2->req('GET', '/app');
check('OUTRA sessão aberta é desconectada', $s === 303 && $ses2->header('Location') === '/entrar');
Db::run('DELETE FROM rate_events');
$old = new Client();
[$s] = $old->post('/entrar', ['email' => 'maria@example.com', 'password' => 'nova-senha-9999']);
check('senha antiga não funciona mais', $s === 401);
check('troca de senha auditada', (int) Db::one("SELECT COUNT(*) c FROM audit_log WHERE action='password_changed'")['c'] === 1);

$ses3 = new Client();
Db::run('DELETE FROM rate_events');
$ses3->post('/entrar', ['email' => 'maria@example.com', 'password' => 'outra-senha-1234']);
$ses4 = new Client();
$ses4->post('/recuperar-senha', ['email' => 'maria@example.com']);
$mails = glob(BASE_PATH . '/storage/mail/*.txt') ?: [];
rsort($mails);
preg_match('#/redefinir-senha/([0-9a-f]{64})#', (string) file_get_contents($mails[0]), $mm);
$ses4->post("/redefinir-senha/{$mm[1]}", ['password' => 'final-senha-77777', 'password2' => 'final-senha-77777'], "/redefinir-senha/{$mm[1]}");
[$s] = $ses3->req('GET', '/app');
check('redefinição por e-mail também desconecta sessões abertas', $s === 303);

echo "\nResultado: $pass ok, $fail falha(s)\n";
exit($fail ? 1 : 0);
