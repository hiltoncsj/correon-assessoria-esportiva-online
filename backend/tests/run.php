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
foreach (['workout_template_items', 'workout_templates', 'workout_log_revisions', 'workout_logs', 'workout_versions', 'workout_items', 'workouts', 'trainer_athletes', 'modalities', 'athlete_profiles', 'audit_log', 'consents', 'password_resets', 'rate_events', 'user_roles', 'users', 'schema_migrations'] as $t) {
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

echo "Sprint 3 — treinos\n";
$cli = static function (string $script, array $args) use ($php): int {
    exec(escapeshellarg($php) . ' ' . escapeshellarg(__DIR__ . "/../bin/$script") . ' ' . implode(' ', array_map('escapeshellarg', $args)), $o, $c);
    return $c;
};
putenv('CORREON_NEW_PASSWORD=senha-staff-12345');
$cli('create-user.php', ['trainer', 'Treinador Dois', 'trn2@example.com']);
$uid = static fn (string $email): int => (int) Db::one('SELECT id FROM users WHERE email = ?', [$email])['id'];
$mariaId = $uid('maria@example.com'); $joaoId = $uid('joao@example.com');
check('CLI vincula treinador a atleta (corrida)', $cli('assign-trainer.php', ['assign', 'trn@example.com', 'maria@example.com', 'corrida']) === 0);
check('CLI vincula treinador 2 a João (musculação)', $cli('assign-trainer.php', ['assign', 'trn2@example.com', 'joao@example.com', 'musculacao']) === 0);
check('CLI recusa perfil errado (atleta como treinador)', $cli('assign-trainer.php', ['assign', 'maria@example.com', 'joao@example.com', 'corrida']) !== 0);

Db::run('DELETE FROM rate_events');
$tr1 = new Client(); $tr1->post('/entrar', ['email' => 'trn@example.com', 'password' => 'senha-staff-12345']);
$tr2 = new Client(); $tr2->post('/entrar', ['email' => 'trn2@example.com', 'password' => 'senha-staff-12345']);
$mar = new Client(); $mar->post('/entrar', ['email' => 'maria@example.com', 'password' => 'final-senha-77777']);
$joa = new Client(); $joa->post('/entrar', ['email' => 'joao@example.com', 'password' => 'senha-forte-123']);

[$s, $b] = $tr1->req('GET', '/treinador');
check('treinador vê a própria carteira', $s === 200 && str_contains($b, 'Maria') && !str_contains($b, 'João Outro'));
[$s] = $tr2->req('GET', "/treinador/atletas/$mariaId");
check('treinador sem vínculo NÃO acessa atleta alheio (404)', $s === 404);
[$s] = $tr1->req('GET', "/treinador/atletas/$joaoId");
check('treinador 1 não acessa atleta do treinador 2 (404)', $s === 404);
[$s] = $tr1->req('GET', "/treinador/atletas/$mariaId");
check('treinador acessa atleta vinculado', $s === 200);
[$s] = $tr1->req('GET', "/treinador/treinos/novo?atleta=$mariaId&modalidade=corrida");
check('formulário de novo treino (corrida) = 200', $s === 200);
[$s] = $tr1->req('GET', "/treinador/treinos/novo?atleta=$mariaId&modalidade=musculacao");
check('modalidade sem vínculo = 404', $s === 404);
[$s] = $mar->req('GET', "/treinador/treinos/novo?atleta=$mariaId&modalidade=corrida");
check('atleta no painel do treinador = 403', $s === 403);
[$s] = $adm->req('GET', '/treinador');
check('admin no painel do treinador = 403', $s === 403);
[$s] = $anon->req('POST', '/treinador/treinos/novo', ['title' => 'x']);
check('POST de treino sem CSRF = 419', $s === 419);

$yesterday = date('Y-m-d', strtotime('-1 day'));
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$run = static fn (array $over = []) => $over + ['athlete_id' => $mariaId, 'modality' => 'corrida', 'title' => 'Rodagem <i>leve</i>',
    'scheduled_date' => $yesterday, 'notes' => 'Controle o ritmo', 'action' => 'draft',
    'items' => [['label' => 'Aquecimento', 'duration_minutes' => '10', 'target' => 'Z1'], ['label' => 'Principal', 'duration_minutes' => '30', 'distance_km' => '5,5', 'target' => '5:30/km']]];
$form = "/treinador/treinos/novo?atleta=$mariaId&modalidade=corrida";
foreach ([
    'título vazio' => ['title' => ''],
    'data inválida' => ['scheduled_date' => '2026-02-31'],
    'duração inválida' => ['items' => [['label' => 'X', 'duration_minutes' => 'abc']]],
    'distância inválida' => ['items' => [['label' => 'X', 'distance_km' => '-3']]],
    'linha sem etapa' => ['items' => [['label' => '', 'duration_minutes' => '10']]],
    'publicar sem linhas' => ['action' => 'publish', 'items' => []],
] as $label => $over) {
    [$s] = $tr1->post('/treinador/treinos/novo', $over + $run(), $form);
    check("treino rejeita: $label (422)", $s === 422, "status=$s");
}
[$s] = $tr1->post('/treinador/treinos/novo', $run(['modality' => 'musculacao']), $form);
check('criar treino em modalidade sem vínculo = 404', $s === 404);
[$s] = $tr1->post('/treinador/treinos/novo', $run(), $form);
$wid = (int) preg_replace('#.*/#', '', (string) $tr1->header('Location'));
check('rascunho criado (303 para o treino)', $s === 303 && $wid > 0);
$w = Db::one('SELECT * FROM workouts WHERE id = ?', [$wid]);
check('rascunho: status draft, versão 1, discipline corrida', ($w['status'] ?? '') === 'draft' && (int) $w['version'] === 1 && $w['discipline'] === 'corrida');
check('itens gravados (distância 5,5 → 5.50)', Db::one('SELECT distance_km d FROM workout_items WHERE workout_id = ? AND label = ?', [$wid, 'Principal'])['d'] === '5.50');

[$s, $b] = $mar->req('GET', '/app/treinos?mes=' . substr($yesterday, 0, 7));
check('atleta NÃO vê rascunho na lista', $s === 200 && !str_contains($b, 'Rodagem'));
[$s] = $mar->req('GET', "/app/treinos/$wid");
check('atleta NÃO abre rascunho (404)', $s === 404);
[$s, $b] = $tr1->req('GET', "/treinador/treinos/$wid");
check('treinador abre o treino; HTML do título escapado', $s === 200 && str_contains($b, '&lt;i&gt;leve') && !str_contains($b, '<i>leve</i>'));

$upd = static fn (string $action, int $v, array $over = []) => $over + ['action' => $action, 'version' => (string) $v, 'title' => 'Rodagem <i>leve</i>',
    'scheduled_date' => $yesterday, 'notes' => 'Controle o ritmo',
    'items' => [['label' => 'Aquecimento', 'duration_minutes' => '10'], ['label' => 'Principal', 'duration_minutes' => '30', 'distance_km' => '5']]];
[$s] = $tr2->post("/treinador/treinos/$wid", $upd('publish', 1), "/treinador/atletas/$joaoId");
check('treinador sem vínculo não altera treino alheio (404)', $s === 404);
[$s] = $tr1->post("/treinador/treinos/$wid", $upd('publish', 1), "/treinador/treinos/$wid");
check('publicar treino', $s === 303);
$w = Db::one('SELECT status, version FROM workouts WHERE id = ?', [$wid]);
check('status published, versão 2', $w['status'] === 'published' && (int) $w['version'] === 2);
check('histórico guarda 2 versões (criado, publicado)', (int) Db::one('SELECT COUNT(*) c FROM workout_versions WHERE workout_id = ?', [$wid])['c'] === 2);
[$s] = $tr1->post("/treinador/treinos/$wid", $upd('save', 1, ['title' => 'Título desatualizado']), "/treinador/treinos/$wid");
check('edição com versão antiga é recusada (conflito)', $s === 303 && Db::one('SELECT title FROM workouts WHERE id = ?', [$wid])['title'] === 'Rodagem <i>leve</i>'
    && (int) Db::one('SELECT version v FROM workouts WHERE id = ?', [$wid])['v'] === 2);

[$s, $b] = $mar->req('GET', '/app/treinos?mes=' . substr($yesterday, 0, 7));
check('atleta vê treino publicado na lista (título escapado)', $s === 200 && str_contains($b, 'Rodagem &lt;i&gt;leve') && str_contains($b, 'Sem registro'));
[$s, $b] = $mar->req('GET', "/app/treinos/$wid");
check('atleta abre o treino e vê as etapas', $s === 200 && str_contains($b, 'Aquecimento') && str_contains($b, '5 km'));
[$s] = $joa->req('GET', "/app/treinos/$wid");
check('outro atleta NÃO abre o treino (404)', $s === 404);
[$s] = $joa->post("/app/treinos/$wid/registro", ['outcome' => 'completed', 'performed_on' => $yesterday], '/app/treinos');
check('outro atleta NÃO registra treino alheio (404)', $s === 404);
[$s] = $tr2->req('GET', "/treinador/treinos/$wid");
check('treinador sem vínculo NÃO abre o treino (404)', $s === 404);

echo "Registro do treino realizado\n";
$log = static fn (array $over = []) => $over + ['outcome' => 'completed', 'performed_on' => $yesterday, 'duration_minutes' => '42', 'distance_km' => '6,2', 'effort' => '7', 'notes' => 'Foi bem <b>mesmo</b>'];
foreach (['esforço 11' => ['effort' => '11'], 'duração 0' => ['duration_minutes' => '0'], 'data futura' => ['performed_on' => $tomorrow],
    'resultado inválido' => ['outcome' => 'x'], 'distância inválida' => ['distance_km' => 'abc']] as $label => $over) {
    [$s] = $mar->post("/app/treinos/$wid/registro", $log($over), "/app/treinos/$wid");
    check("registro rejeita: $label (422)", $s === 422, "status=$s");
}
[$s] = $mar->post("/app/treinos/$wid/registro", $log(), "/app/treinos/$wid");
check('atleta registra o treino realizado', $s === 303);
$l = Db::one('SELECT * FROM workout_logs WHERE workout_id = ?', [$wid]);
check('registro gravado separado do planejado', $l && $l['outcome'] === 'completed' && $l['distance_km'] === '6.20' && (int) $l['effort'] === 7);
check('planejado não foi alterado pelo registro', Db::one('SELECT title FROM workouts WHERE id = ?', [$wid])['title'] === 'Rodagem <i>leve</i>');
[$s, $b] = $mar->req('GET', "/app/treinos/$wid");
check('comentário do registro escapado (XSS)', !str_contains($b, '<b>mesmo</b>') && str_contains($b, '&lt;b&gt;mesmo'));
[$s, $b] = $tr1->req('GET', "/treinador/treinos/$wid");
check('treinador vê o registro do atleta', str_contains($b, 'Registro do atleta') && str_contains($b, '6,2 km'));
[$s] = $tr1->post("/treinador/treinos/$wid", $upd('save', 2, ['title' => 'Tentando alterar']), "/treinador/treinos/$wid");
check('treino já realizado NÃO pode ser alterado pelo treinador', Db::one('SELECT title FROM workouts WHERE id = ?', [$wid])['title'] === 'Rodagem <i>leve</i>');
[$s] = $tr1->post("/treinador/treinos/$wid", $upd('cancel', 2), "/treinador/treinos/$wid");
check('treino já realizado NÃO pode ser cancelado', Db::one('SELECT status FROM workouts WHERE id = ?', [$wid])['status'] === 'published');
[$s] = $mar->post("/app/treinos/$wid/registro", $log(['outcome' => 'partial', 'duration_minutes' => '30']), "/app/treinos/$wid");
check('atleta corrige o próprio registro', $s === 303 && Db::one('SELECT outcome FROM workout_logs WHERE workout_id = ?', [$wid])['outcome'] === 'partial');
check('versão anterior do registro preservada (revisão)', (int) Db::one('SELECT COUNT(*) c FROM workout_log_revisions')['c'] === 1);

echo "Treino futuro, cancelamento e vínculo encerrado\n";
$fut = static fn (array $over = []) => $over + $run(['scheduled_date' => $tomorrow, 'title' => 'Longo de amanhã', 'action' => 'publish']);
[$s] = $tr1->post('/treinador/treinos/novo', $fut(), $form);
$fid = (int) preg_replace('#.*/#', '', (string) $tr1->header('Location'));
check('treino futuro publicado direto', $s === 303 && Db::one('SELECT status FROM workouts WHERE id = ?', [$fid])['status'] === 'published');
[$s] = $mar->post("/app/treinos/$fid/registro", $log(['performed_on' => $yesterday]), "/app/treinos/$fid");
check('atleta não registra treino antes do dia dele', $s === 303 && !Db::one('SELECT id FROM workout_logs WHERE workout_id = ?', [$fid]));
[$s, $b] = $mar->req('GET', '/app');
check('dashboard mostra próximo treino e contadores reais', str_contains($b, 'Longo de amanhã') && str_contains($b, '1 treino(s) programado(s) · 1 realizado(s)'));
check('dashboard mostra o treinador vinculado', str_contains($b, 'Staff trainer'));
[$s, $b] = $mar->req('GET', '/app/treinador');
check('/app/treinador lista o treinador real', $s === 200 && str_contains($b, 'Staff trainer') && str_contains($b, 'Corrida'));
[$s] = $tr1->post("/treinador/treinos/$fid", ['action' => 'unpublish', 'version' => '1', 'title' => 'x', 'scheduled_date' => $tomorrow], "/treinador/treinos/$fid");
[$s] = $mar->req('GET', "/app/treinos/$fid");
check('treino despublicado some para o atleta (404)', $s === 404);
[$s] = $tr1->post("/treinador/treinos/$fid", ['action' => 'cancel', 'version' => '2', 'title' => 'x', 'scheduled_date' => $tomorrow], "/treinador/treinos/$fid");
check('treino cancelado', Db::one('SELECT status FROM workouts WHERE id = ?', [$fid])['status'] === 'cancelled');
[$s] = $tr1->post("/treinador/treinos/$fid", $upd('publish', 3), "/treinador/treinos/$fid");
check('treino cancelado não volta a ser editado', Db::one('SELECT status FROM workouts WHERE id = ?', [$fid])['status'] === 'cancelled');
check('CLI encerra o vínculo', $cli('assign-trainer.php', ['end', 'trn@example.com', 'maria@example.com', 'corrida']) === 0);
[$s] = $tr1->req('GET', "/treinador/treinos/$wid");
check('vínculo encerrado: treinador perde o acesso (404)', $s === 404);
[$s] = $tr1->req('GET', "/treinador/atletas/$mariaId");
check('vínculo encerrado: perfil do atleta some (404)', $s === 404);
[$s] = $mar->req('GET', "/app/treinos/$wid");
check('vínculo encerrado: atleta mantém o histórico', $s === 200);

echo "Musculação\n";
$strength = static fn (array $over = []) => $over + ['athlete_id' => $joaoId, 'modality' => 'musculacao', 'title' => 'Treino A', 'scheduled_date' => $yesterday,
    'notes' => '', 'action' => 'publish', 'items' => [['label' => 'Supino reto', 'sets' => '4', 'reps' => '8-10', 'load_text' => '40 kg', 'rest_seconds' => '90'],
    ['label' => 'Remada curvada', 'sets' => '3', 'reps' => '12', 'load_text' => '30 kg', 'rest_seconds' => '60']]];
$sform = "/treinador/treinos/novo?atleta=$joaoId&modalidade=musculacao";
[$s] = $tr2->post('/treinador/treinos/novo', $strength(['items' => [['label' => 'Supino', 'sets' => '0']]]), $sform);
check('séries inválidas rejeitadas (422)', $s === 422);
[$s] = $tr2->post('/treinador/treinos/novo', $strength(['items' => [['label' => 'Supino', 'rest_seconds' => '99999']]]), $sform);
check('intervalo inválido rejeitado (422)', $s === 422);
[$s] = $tr2->post('/treinador/treinos/novo', $strength(), $sform);
$sid = (int) preg_replace('#.*/#', '', (string) $tr2->header('Location'));
check('treino de musculação publicado', $s === 303 && $sid > 0 && Db::one('SELECT discipline d FROM workouts WHERE id = ?', [$sid])['d'] === 'musculacao');
[$s, $b] = $joa->req('GET', "/app/treinos/$sid");
check('atleta vê exercícios, séries, repetições e carga', $s === 200 && str_contains($b, 'Supino reto') && str_contains($b, '8-10') && str_contains($b, '40 kg') && str_contains($b, '90 s'));
check('musculação não pede distância no registro', !str_contains($b, 'name="distance_km"'));
[$s] = $tr1->req('GET', "/treinador/treinos/$sid");
check('treinador de corrida NÃO abre treino de musculação (404)', $s === 404);

echo "Triathlon\n";
$cli('create-user.php', ['trainer', 'Treinador Tri', 'tri@example.com']);
$cli('assign-trainer.php', ['assign', 'tri@example.com', 'joao@example.com', 'triathlon']);
Db::run('DELETE FROM rate_events');
$tri = new Client(); $tri->post('/entrar', ['email' => 'tri@example.com', 'password' => 'senha-staff-12345']);
$tform = "/treinador/treinos/novo?atleta=$joaoId&modalidade=triathlon";
[$s, $b] = $tri->req('GET', $tform);
check('triathlon oferece natação, ciclismo e corrida', $s === 200 && str_contains($b, 'Natação') && str_contains($b, 'Ciclismo') && str_contains($b, 'Corrida'));
$tp = ['athlete_id' => $joaoId, 'modality' => 'triathlon', 'title' => 'Pedal base', 'scheduled_date' => $tomorrow, 'notes' => '', 'action' => 'publish',
    'discipline' => 'ciclismo', 'items' => [['label' => 'Z2 contínuo', 'duration_minutes' => '90', 'distance_km' => '40']]];
[$s] = $tri->post('/treinador/treinos/novo', ['discipline' => 'musculacao'] + $tp, $tform);
check('triathlon rejeita disciplina de musculação (422)', $s === 422);
[$s] = $tri->post('/treinador/treinos/novo', $tp, $tform);
check('treino de ciclismo criado no triathlon', $s === 303 && Db::one("SELECT COUNT(*) c FROM workouts WHERE discipline = 'ciclismo'")['c'] == 1);

echo "Auditoria do treino\n";
check('ações de treino auditadas', (int) Db::one("SELECT COUNT(*) c FROM audit_log WHERE action IN ('workout_created','workout_published','workout_updated','workout_cancelled','workout_logged','workout_log_updated')")['c'] >= 5);
check('histórico de versões guarda foto completa', str_contains((string) Db::one('SELECT snapshot s FROM workout_versions WHERE workout_id = ? AND version = 2', [$wid])['s'], 'Aquecimento'));

echo "Modelos de treino (Treino 1, 2, 3…)\n";
Db::run('DELETE FROM rate_events');
$ana = new Client(); register($ana, ['email' => 'ana@example.com', 'name' => 'Ana Corredora']);
$anaId = $uid('ana@example.com');
$cli('assign-trainer.php', ['assign', 'trn@example.com', 'maria@example.com', 'corrida']);
$cli('assign-trainer.php', ['assign', 'trn@example.com', 'ana@example.com', 'corrida']);
Db::run('DELETE FROM rate_events');
$tr1 = new Client(); $tr1->post('/entrar', ['email' => 'trn@example.com', 'password' => 'senha-staff-12345']);

[$s, $b] = $tr1->req('GET', '/treinador/modelos');
check('biblioteca de modelos = 200 (vazia)', $s === 200 && str_contains($b, 'Nenhum modelo criado'));
[$s] = $mar->req('GET', '/treinador/modelos');
check('atleta não acessa modelos (403)', $s === 403);
[$s] = $tr1->req('GET', '/treinador/modelos/novo?modalidade=musculacao');
check('modelo em modalidade sem atletas = 404', $s === 404);

$tplForm = '/treinador/modelos/novo?modalidade=corrida';
$t1 = ['modality' => 'corrida', 'name' => 'Treino 1', 'notes' => '', 'items' => [
    ['label' => 'Aquecimento', 'duration_minutes' => '10', 'target' => 'Z1'], ['label' => 'Tiros 6x400m', 'duration_minutes' => '25', 'target' => '4:30/km']]];
foreach (['sem nome' => ['name' => ''], 'sem linhas' => ['items' => []], 'duração inválida' => ['items' => [['label' => 'X', 'duration_minutes' => 'abc']]]] as $label => $over) {
    [$s] = $tr1->post('/treinador/modelos/novo', $over + $t1, $tplForm);
    check("modelo rejeita: $label (422)", $s === 422, "status=$s");
}
[$s] = $tr1->post('/treinador/modelos/novo', $t1, $tplForm);
$tpl1 = (int) preg_replace('#.*/#', '', (string) $tr1->header('Location'));
check('Treino 1 criado', $s === 303 && $tpl1 > 0);
[$s] = $tr1->post('/treinador/modelos/novo', ['name' => 'Treino 2', 'items' => [['label' => 'Longo contínuo', 'duration_minutes' => '60', 'distance_km' => '10']]] + $t1, $tplForm);
$tpl2 = (int) preg_replace('#.*/#', '', (string) $tr1->header('Location'));
check('Treino 2 criado', $s === 303 && $tpl2 > 0);
[$s, $b] = $tr1->req('GET', '/treinador/modelos');
check('biblioteca lista Treino 1 e Treino 2', str_contains($b, 'Treino 1') && str_contains($b, 'Treino 2'));

[$s] = $tr2->req('GET', "/treinador/modelos/$tpl1");
check('outro treinador NÃO abre o modelo (404)', $s === 404);
[$s] = $tr2->post("/treinador/modelos/$tpl1", ['action' => 'archive'], "/treinador/modelos");
check('outro treinador NÃO arquiva o modelo (404)', $s === 404 && Db::one('SELECT archived_at a FROM workout_templates WHERE id = ?', [$tpl1])['a'] === null);
[$s, $b] = $tr2->req('GET', '/treinador/modelos');
check('lista de modelos do outro treinador não mostra os meus', !str_contains($b, '/treinador/modelos/' . $tpl1));

[$s, $b] = $tr1->req('GET', '/treinador/modelos/aplicar?modalidade=corrida');
check('tela de aplicar mostra meus atletas e modelos', $s === 200 && str_contains($b, 'Maria') && str_contains($b, 'Ana Corredora') && str_contains($b, 'Treino 1') && str_contains($b, 'Treino 2'));
check('...e NÃO mostra atleta de outro treinador', !str_contains($b, 'João Outro'));

$aplicar = "/treinador/modelos/aplicar?modalidade=corrida";
$day = date('Y-m-d', strtotime('+3 days'));
$ap = static fn (array $over = []) => $over + ['modality' => 'corrida', 'scheduled_date' => $day, 'publish' => '1', 'pick' => [$mariaId => (string) $tpl1, $anaId => (string) $tpl2]];
[$s] = $tr1->post('/treinador/modelos/aplicar', $ap(['scheduled_date' => 'ontem']), $aplicar);
check('aplicar com data inválida não cria nada', (int) Db::one('SELECT COUNT(*) c FROM workouts WHERE source_template_id IS NOT NULL')['c'] === 0);
[$s] = $tr1->post('/treinador/modelos/aplicar', $ap(['pick' => [$mariaId => '']]), $aplicar);
check('aplicar sem escolher treino não cria nada', (int) Db::one('SELECT COUNT(*) c FROM workouts WHERE source_template_id IS NOT NULL')['c'] === 0);
[$s] = $tr1->post('/treinador/modelos/aplicar', $ap(['pick' => [$mariaId => (string) $tpl1, $joaoId => (string) $tpl1]]), $aplicar);
check('atleta de outro treinador na seleção: tudo recusado', (int) Db::one('SELECT COUNT(*) c FROM workouts WHERE source_template_id IS NOT NULL')['c'] === 0);
[$s] = $tr1->post('/treinador/modelos/aplicar', $ap(['pick' => [$mariaId => (string) ($tpl2 + 9999)]]), $aplicar);
check('modelo inexistente/alheio na seleção: recusado', (int) Db::one('SELECT COUNT(*) c FROM workouts WHERE source_template_id IS NOT NULL')['c'] === 0);

[$s] = $tr1->post('/treinador/modelos/aplicar', $ap(), $aplicar);
check('aplicar: Maria→Treino 1, Ana→Treino 2', $s === 303);
$wm = Db::one('SELECT * FROM workouts WHERE athlete_id = ? AND source_template_id = ?', [$mariaId, $tpl1]);
$wa = Db::one('SELECT * FROM workouts WHERE athlete_id = ? AND source_template_id = ?', [$anaId, $tpl2]);
check('cada atleta recebeu o seu treino, publicado, na data escolhida', $wm && $wa && $wm['status'] === 'published' && $wa['scheduled_date'] === $day && $wm['title'] === 'Treino 1' && $wa['title'] === 'Treino 2');
check('linhas copiadas do modelo', (int) Db::one('SELECT COUNT(*) c FROM workout_items WHERE workout_id = ?', [$wm['id']])['c'] === 2
    && Db::one('SELECT distance_km d FROM workout_items WHERE workout_id = ?', [$wa['id']])['d'] === '10.00');
check('histórico de versões criado para cada cópia', (int) Db::one('SELECT COUNT(*) c FROM workout_versions WHERE workout_id IN (?,?)', [$wm['id'], $wa['id']])['c'] === 2);
[$s, $b] = $mar->req('GET', '/app/treinos?mes=' . substr($day, 0, 7));
check('Maria vê o Treino 1 e não o Treino 2', str_contains($b, 'Treino 1') && !str_contains($b, 'Treino 2'));
[$s, $b] = $ana->req('GET', '/app/treinos?mes=' . substr($day, 0, 7));
check('Ana vê o Treino 2 e não o Treino 1', str_contains($b, 'Treino 2') && !str_contains($b, 'Treino 1'));

[$s] = $tr1->post('/treinador/modelos/aplicar', $ap(), $aplicar);
check('aplicar de novo (mesmo dia e modelo) não duplica', (int) Db::one('SELECT COUNT(*) c FROM workouts WHERE source_template_id IS NOT NULL')['c'] === 2);

echo "Edição individual\n";
[$s, $b] = $tr1->req('GET', "/treinador/treinos/{$wm['id']}");
check('treino do atleta indica o modelo de origem', $s === 200 && str_contains($b, 'criado a partir do modelo') && str_contains($b, 'Treino 1'));
$ed = ['action' => 'save', 'version' => (string) $wm['version'], 'title' => 'Treino 1 (ajustado p/ Maria)', 'scheduled_date' => $day, 'notes' => 'Menos volume nesta semana',
    'items' => [['label' => 'Aquecimento', 'duration_minutes' => '15', 'target' => 'Z1'], ['label' => 'Tiros 4x400m', 'duration_minutes' => '20', 'target' => '4:45/km']]];
[$s] = $tr1->post("/treinador/treinos/{$wm['id']}", $ed, "/treinador/treinos/{$wm['id']}");
check('treinador edita o treino de UM atleta', $s === 303 && Db::one('SELECT title FROM workouts WHERE id = ?', [$wm['id']])['title'] === 'Treino 1 (ajustado p/ Maria)');
check('o modelo original não mudou', Db::one('SELECT name FROM workout_templates WHERE id = ?', [$tpl1])['name'] === 'Treino 1'
    && Db::one('SELECT duration_minutes d FROM workout_template_items WHERE template_id = ? AND position = 1', [$tpl1])['d'] == 10);
$tpl1again = ['action' => 'save', 'name' => 'Treino 1', 'notes' => '', 'items' => [['label' => 'Só um bloco', 'duration_minutes' => '45']]];
[$s] = $tr1->post("/treinador/modelos/$tpl1", $tpl1again, "/treinador/modelos/$tpl1");
check('editar o modelo depois', $s === 303 && (int) Db::one('SELECT COUNT(*) c FROM workout_template_items WHERE template_id = ?', [$tpl1])['c'] === 1);
check('...não altera treinos já aplicados', Db::one('SELECT title FROM workouts WHERE id = ?', [$wm['id']])['title'] === 'Treino 1 (ajustado p/ Maria)'
    && (int) Db::one('SELECT COUNT(*) c FROM workout_items WHERE workout_id = ?', [$wm['id']])['c'] === 2);

echo "Rascunho, arquivar e musculação\n";
$day2 = date('Y-m-d', strtotime('+4 days'));
[$s] = $tr1->post('/treinador/modelos/aplicar', $ap(['scheduled_date' => $day2, 'publish' => '0', 'pick' => [$anaId => (string) $tpl1]]), $aplicar);
$wd = Db::one('SELECT * FROM workouts WHERE athlete_id = ? AND scheduled_date = ?', [$anaId, $day2]);
check('aplicar como rascunho', $wd && $wd['status'] === 'draft');
[$s, $b] = $ana->req('GET', '/app/treinos?mes=' . substr($day2, 0, 7));
check('rascunho aplicado não aparece para o atleta', !str_contains($b, 'Treino 1'));
[$s] = $tr1->post("/treinador/modelos/$tpl2", ['action' => 'archive'], "/treinador/modelos/$tpl2");
check('arquivar modelo', Db::one('SELECT archived_at a FROM workout_templates WHERE id = ?', [$tpl2])['a'] !== null);
[$s, $b] = $tr1->req('GET', '/treinador/modelos/aplicar?modalidade=corrida');
check('modelo arquivado some da tela de aplicar', !str_contains($b, 'Treino 2'));
[$s] = $tr1->post('/treinador/modelos/aplicar', $ap(['scheduled_date' => $day2, 'pick' => [$mariaId => (string) $tpl2]]), $aplicar);
check('modelo arquivado não pode ser aplicado', (int) Db::one('SELECT COUNT(*) c FROM workouts WHERE athlete_id = ? AND scheduled_date = ?', [$mariaId, $day2])['c'] === 0);
check('treino já aplicado do modelo arquivado continua com a Ana', Db::one('SELECT status FROM workouts WHERE id = ?', [$wa['id']])['status'] === 'published');

$mf = '/treinador/modelos/novo?modalidade=musculacao';
[$s] = $tr2->post('/treinador/modelos/novo', ['modality' => 'musculacao', 'name' => 'Treino A', 'notes' => '', 'items' => [
    ['label' => 'Agachamento', 'sets' => '4', 'reps' => '10', 'load_text' => '60 kg', 'rest_seconds' => '90']]], $mf);
$mt = (int) preg_replace('#.*/#', '', (string) $tr2->header('Location'));
check('modelo de musculação criado', $s === 303 && $mt > 0);
$day3 = date('Y-m-d', strtotime('+5 days'));
[$s] = $tr2->post('/treinador/modelos/aplicar', ['modality' => 'musculacao', 'scheduled_date' => $day3, 'publish' => '1', 'pick' => [$joaoId => (string) $mt]], '/treinador/modelos/aplicar?modalidade=musculacao');
$wj = Db::one('SELECT id, discipline FROM workouts WHERE athlete_id = ? AND source_template_id = ?', [$joaoId, $mt]);
check('modelo de musculação aplicado ao atleta', $wj && $wj['discipline'] === 'musculacao');
[$s, $b] = $joa->req('GET', "/app/treinos/{$wj['id']}");
check('atleta vê séries, repetições e carga copiadas', str_contains($b, 'Agachamento') && str_contains($b, '60 kg') && str_contains($b, '90 s'));
check('aplicação auditada', (int) Db::one("SELECT COUNT(*) c FROM audit_log WHERE action = 'workout_from_template'")['c'] >= 4);

echo "\nResultado: $pass ok, $fail falha(s)\n";
exit($fail ? 1 : 0);
