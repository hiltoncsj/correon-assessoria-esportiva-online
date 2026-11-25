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
foreach (['workout_template_items', 'workout_template_sessions', 'workout_templates', 'athlete_hr_zones', 'athlete_hr_settings', 'workout_log_revisions', 'workout_logs', 'workout_versions', 'workout_items', 'workouts', 'trainer_athletes', 'modalities', 'athlete_profiles', 'audit_log', 'consents', 'password_resets', 'rate_events', 'user_roles', 'users', 'schema_migrations'] as $t) {
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

function Workout_maxhr(int $id): ?int { $r = \Correon\Db::one("SELECT max_hr m FROM athlete_hr_settings WHERE athlete_id = ?", [$id]); return $r ? (int) $r["m"] : null; }
echo "Zonas de FC\n";
Db::run('DELETE FROM rate_events');
$ana = new Client(); register($ana, ['email' => 'ana@example.com', 'name' => 'Ana Corredora']);
$anaId = $uid('ana@example.com');
$cli('assign-trainer.php', ['assign', 'trn@example.com', 'maria@example.com', 'corrida']);
$cli('assign-trainer.php', ['assign', 'trn@example.com', 'ana@example.com', 'corrida']);
Db::run('DELETE FROM rate_events');
$tr1 = new Client(); $tr1->post('/entrar', ['email' => 'trn@example.com', 'password' => 'senha-staff-12345']);
$zonesOf = static fn (int $id) => Db::all('SELECT zone, bpm_min, bpm_max FROM athlete_hr_zones WHERE athlete_id = ? ORDER BY zone', [$id]);
$zp = static fn (array $z) => ['action' => 'save', 'zones' => array_map(static fn ($r) => ['min' => (string) $r[0], 'max' => (string) $r[1]], $z)];
$mariaPage = "/treinador/atletas/$mariaId";

$tr1->post("$mariaPage/zonas", $zp([1 => [130, 120]]), $mariaPage);
check('zona com mínimo maior que o máximo é recusada', count($zonesOf($mariaId)) === 0);
$tr1->post("$mariaPage/zonas", $zp([1 => [120, 300]]), $mariaPage);
check('zona acima de 230 bpm é recusada', count($zonesOf($mariaId)) === 0);
$tr1->post("$mariaPage/zonas", $zp([1 => [140, 150], 2 => [120, 130]]), $mariaPage);
check('zonas fora de ordem são recusadas', count($zonesOf($mariaId)) === 0);
$tr1->post("$mariaPage/zonas", ['action' => 'save', 'zones' => [1 => ['min' => 'abc', 'max' => '130']]], $mariaPage);
check('zona não numérica é recusada', count($zonesOf($mariaId)) === 0);
[$s] = $tr1->post("$mariaPage/zonas", $zp([1 => [120, 130], 2 => [131, 145], 3 => [146, 158], 4 => [159, 170], 5 => [171, 185]]), $mariaPage);
check('treinador salva as 5 zonas da Maria', $s === 303 && count($zonesOf($mariaId)) === 5);
[$s] = $tr1->post("/treinador/atletas/$anaId/zonas", $zp([1 => [142, 151], 2 => [152, 160]]), "/treinador/atletas/$anaId");
check('zonas da Ana (só Z1 e Z2) — valores diferentes dos da Maria', $s === 303 && count($zonesOf($anaId)) === 2);
[$s] = $tr2->post("/treinador/atletas/$mariaId/zonas", $zp([1 => [100, 110]]), "/treinador/atletas/$joaoId");
check('treinador sem vínculo NÃO altera as zonas (404)', $s === 404 && $zonesOf($mariaId)[0]['bpm_min'] == 120);
[$s] = $mar->req('POST', "/treinador/atletas/$mariaId/zonas", $zp([1 => [100, 110]]));
check('atleta não altera as próprias zonas pelo painel do treinador (403/419)', in_array($s, [403, 419], true) && $zonesOf($mariaId)[0]['bpm_min'] == 120);
[$s, $b] = $mar->req('GET', '/app/perfil');
check('atleta vê suas zonas (somente leitura)', $s === 200 && str_contains($b, '120–130 bpm') && str_contains($b, '171–185 bpm'));
check('auditoria das zonas não guarda os valores de bpm', Db::one("SELECT id FROM audit_log WHERE action = 'hr_zones_updated'") !== null
    && Db::one("SELECT id FROM audit_log WHERE action = 'hr_zones_updated' AND details LIKE '%130%'") === null);

echo "Modelos semanais (Treino 1 a 4)\n";
[$s, $b] = $tr1->req('GET', '/treinador/modelos');
check('biblioteca de modelos = 200 (vazia)', $s === 200 && str_contains($b, 'Nenhum modelo criado'));
[$s] = $mar->req('GET', '/treinador/modelos');
check('atleta não acessa modelos (403)', $s === 403);
[$s] = $tr1->req('GET', '/treinador/modelos/novo?modalidade=musculacao');
check('modelo em modalidade sem atletas = 404', $s === 404);

$tplForm = '/treinador/modelos/novo?modalidade=corrida';
$aplicar = '/treinador/modelos/aplicar?modalidade=corrida&semana=' . date('Y-m-d', strtotime('+8 weeks'));
$sess = static fn (int $wd, string $title, array $items, array $over = []) => $over + ['weekday' => (string) $wd, 'title' => $title, 'discipline' => 'corrida', 'notes' => '', 'items' => $items];
$iv = static fn (string $z4 = '4') => [['label' => 'Aquecimento', 'duration_minutes' => '15', 'hr_zone' => '1'], ['label' => 'Tiros 8x400m', 'duration_minutes' => '25', 'hr_zone' => $z4, 'target' => '4:30/km']];
$longo = static fn (string $km = '12') => [['label' => 'Contínuo', 'duration_minutes' => '70', 'distance_km' => $km, 'hr_zone' => '2']];
$T = static fn (string $name, array $sessions, array $over = []) => $over + ['modality' => 'corrida', 'name' => $name, 'level_description' => 'pace acima de 6:30/km', 'sessions' => $sessions];

foreach ([
    'nenhuma sessão' => $T('Treino X', []),
    'sessão sem título' => $T('Treino X', [$sess(2, '', $iv())]),
    'dia da semana inválido' => $T('Treino X', [$sess(9, 'Intervalado', $iv())]),
    'zona de FC 8' => $T('Treino X', [$sess(2, 'Intervalado', $iv('8'))]),
    'sessão sem linhas' => $T('Treino X', [$sess(2, 'Intervalado', [])]),
    'modelo sem nome' => $T('', [$sess(2, 'Intervalado', $iv())]),
] as $label => $data) {
    [$s] = $tr1->post('/treinador/modelos/novo', $data, $tplForm);
    check("modelo rejeita: $label (422)", $s === 422, "status=$s");
}
[$s] = $tr1->post('/treinador/modelos/novo', $T('Treino 1', [$sess(2, 'Intervalado velocidade 1', $iv()), $sess(4, 'Intervalado velocidade 2', $iv('5')), $sess(7, 'Longo', $longo())]), $tplForm);
$tpl1 = (int) preg_replace('#.*/#', '', (string) $tr1->header('Location'));
check('Treino 1 (semana com 3 sessões) criado', $s === 303 && $tpl1 > 0 && (int) Db::one('SELECT COUNT(*) c FROM workout_template_sessions WHERE template_id = ?', [$tpl1])['c'] === 3);
[$s] = $tr1->post('/treinador/modelos/novo', $T('Treino 2', [$sess(2, 'Intervalado', $iv()), $sess(7, 'Longo', $longo('15'))], ['level_description' => 'pace 5:30 a 6:29/km']), $tplForm);
$tpl2 = (int) preg_replace('#.*/#', '', (string) $tr1->header('Location'));
check('Treino 2 criado', $s === 303 && $tpl2 > 0);
check('zona de FC gravada nas linhas do modelo', (int) Db::one('SELECT COUNT(*) c FROM workout_template_items WHERE template_id = ? AND hr_zone = 4', [$tpl1])['c'] === 1);
[$s, $b] = $tr1->req('GET', "/treinador/modelos/$tpl1");
check('modelo abre com as 3 sessões preenchidas', $s === 200 && str_contains($b, 'Intervalado velocidade 2') && str_contains($b, 'Longo'));
[$s, $b] = $tr1->req('GET', '/treinador/modelos');
check('biblioteca lista Treino 1 e Treino 2 com o nível', str_contains($b, '/treinador/modelos/' . $tpl1) && str_contains($b, 'pace acima de 6:30/km') && str_contains($b, 'pace 5:30 a 6:29/km'));
[$s] = $tr2->req('GET', "/treinador/modelos/$tpl1");
check('outro treinador NÃO abre o modelo (404)', $s === 404);
[$s] = $tr2->post("/treinador/modelos/$tpl1", ['action' => 'archive'], '/treinador/modelos');
check('outro treinador NÃO arquiva o modelo (404)', $s === 404 && Db::one('SELECT archived_at a FROM workout_templates WHERE id = ?', [$tpl1])['a'] === null);
[$s, $b] = $tr2->req('GET', '/treinador/modelos');
check('lista de modelos do outro treinador não mostra os meus', !str_contains($b, '/treinador/modelos/' . $tpl1));

echo "Treino padrão por nível\n";
[$s] = $tr1->post("$mariaPage/nivel", ['modality' => 'corrida', 'template_id' => (string) $tpl1], $mariaPage);
check('treino padrão da Maria = Treino 1', $s === 303 && (int) Db::one("SELECT default_template_id d FROM trainer_athletes WHERE athlete_id = ? AND modality_id = 1 AND trainer_id = ?", [$mariaId, $uid('trn@example.com')])['d'] === $tpl1);
$tr1->post("/treinador/atletas/$anaId/nivel", ['modality' => 'corrida', 'template_id' => (string) $tpl2], "/treinador/atletas/$anaId");
$tr2->post('/treinador/modelos/novo', ['modality' => 'musculacao', 'name' => 'Treino A', 'level_description' => '', 'sessions' => [
    ['weekday' => '1', 'title' => 'Peito e tríceps', 'notes' => '', 'items' => [['label' => 'Supino reto', 'sets' => '4', 'reps' => '8-10', 'load_text' => '40 kg', 'rest_seconds' => '90']]],
    ['weekday' => '3', 'title' => 'Costas e bíceps', 'notes' => '', 'items' => [['label' => 'Remada curvada', 'sets' => '3', 'reps' => '12', 'load_text' => '30 kg', 'rest_seconds' => '60']]]]],
    '/treinador/modelos/novo?modalidade=musculacao');
$mtA = (int) preg_replace('#.*/#', '', (string) $tr2->header('Location'));
check('treinador de musculação cria o modelo semanal "Treino A"', $mtA > 0);
[$s] = $tr1->post("$mariaPage/nivel", ['modality' => 'corrida', 'template_id' => (string) $mtA], $mariaPage);
check('treino padrão de modelo de OUTRO treinador é recusado (404)', $s === 404);
[$s, $b] = $tr1->req('GET', $aplicar);
check('apply: Maria já vem marcada no Treino 1 e Ana no Treino 2', str_contains($b, 'name="tpl[' . $tpl1 . '][]" value="' . $mariaId . '" data-athlete="' . $mariaId . '" checked') && str_contains($b, 'name="tpl[' . $tpl2 . '][]" value="' . $anaId . '" data-athlete="' . $anaId . '" checked') && !str_contains($b, 'name="tpl[' . $tpl2 . '][]" value="' . $mariaId . '" data-athlete="' . $mariaId . '" checked'));
check('apply: NÃO mostra atleta de outro treinador nem alerta de zonas (todos configurados)', !str_contains($b, 'João Outro') && !str_contains($b, 'badge--off">sem zonas'));

echo "Aplicar a semana\n";
$mon = date('Y-m-d', strtotime('monday last week'));
$wed = date('Y-m-d', strtotime("$mon +2 days"));
$ap = static fn (array $over = []) => $over + ['modality' => 'corrida', 'scheduled_date' => $wed, 'publish' => '1', 'pick' => [$mariaId => (string) $tpl1, $anaId => (string) $tpl2]];
$count = static fn () => (int) Db::one('SELECT COUNT(*) c FROM workouts WHERE source_template_id IS NOT NULL')['c'];
$tr1->post('/treinador/modelos/aplicar', $ap(['scheduled_date' => 'ontem']), $aplicar);
check('semana com data inválida não cria nada', $count() === 0);
$tr1->post('/treinador/modelos/aplicar', $ap(['pick' => [$mariaId => '']]), $aplicar);
check('aplicar sem escolher treino não cria nada', $count() === 0);
$tr1->post('/treinador/modelos/aplicar', $ap(['pick' => [$mariaId => (string) $tpl1, $joaoId => (string) $tpl1]]), $aplicar);
check('atleta de outro treinador na seleção: tudo recusado', $count() === 0);
$tr1->post('/treinador/modelos/aplicar', $ap(['pick' => [$mariaId => (string) $mtA]]), $aplicar);
check('modelo de outro treinador na seleção: recusado', $count() === 0);

[$s] = $tr1->post('/treinador/modelos/aplicar', $ap(), $aplicar);
check('aplicar a semana: Maria→Treino 1 (3 sessões), Ana→Treino 2 (2 sessões)', $s === 303 && $count() === 5, 'criados=' . $count());
$mw = Db::all('SELECT * FROM workouts WHERE athlete_id = ? AND source_template_id = ? ORDER BY scheduled_date', [$mariaId, $tpl1]);
check('sessões caem nos dias certos da semana (terça, quinta e domingo)', array_column($mw, 'scheduled_date') === [date('Y-m-d', strtotime("$mon +1 day")), date('Y-m-d', strtotime("$mon +3 days")), date('Y-m-d', strtotime("$mon +6 days"))]);
check('treinos publicados com título "modelo · sessão"', $mw[0]['status'] === 'published' && $mw[0]['title'] === 'Treino 1 · Intervalado velocidade 1');
check('linhas e zonas copiadas (Z1 e Z4)', Db::one('SELECT GROUP_CONCAT(hr_zone ORDER BY position) z FROM workout_items WHERE workout_id = ?', [$mw[0]['id']])['z'] === '1,4');
check('histórico de versões criado para cada cópia', (int) Db::one('SELECT COUNT(*) c FROM workout_versions v JOIN workouts w ON w.id = v.workout_id WHERE w.source_template_id IS NOT NULL')['c'] === 5);

echo "Aplicar por treino (vários atletas em cada treino)\n";
$tr1->post('/treinador/modelos/novo', $T('Treino 4', [$sess(3, 'Regenerativo', [['label' => 'Trote', 'duration_minutes' => '30', 'hr_zone' => '1']])], ['level_description' => 'abaixo de 4:29/km']), $tplForm);
$tpl4 = (int) preg_replace('#.*/#', '', (string) $tr1->header('Location'));
$mon2 = date('Y-m-d', strtotime('monday this week -2 weeks'));
$grp = static fn (array $tpl, array $over = []) => $over + ['modality' => 'corrida', 'scheduled_date' => $mon2, 'publish' => '1', 'tpl' => $tpl];
$before = $count();
$tr1->post('/treinador/modelos/aplicar', $grp([$tpl4 => [$mariaId], $tpl1 => [$mariaId]]), $aplicar);
check('mesmo atleta marcado em dois treinos: tudo recusado', $count() === $before);
$tr1->post('/treinador/modelos/aplicar', $grp([$tpl4 => [$mariaId, $joaoId]]), $aplicar);
check('atleta de outro treinador no grupo: tudo recusado', $count() === $before);
[$s, $b] = $tr1->req('GET', $aplicar);
check('tela de aplicar agrupa os atletas por treino (caixas de seleção)', str_contains($b, 'name="tpl[' . $tpl4 . '][]"') && str_contains($b, 'name="tpl[' . $tpl1 . '][]"') && str_contains($b, 'Treino 4'));
[$s] = $tr1->post('/treinador/modelos/aplicar', $grp([$tpl4 => [$mariaId, $anaId]]), $aplicar);
check('Treino 4 enviado para Maria e Ana de uma vez', $s === 303 && $count() === $before + 2 && (int) Db::one('SELECT COUNT(*) c FROM workouts WHERE source_template_id = ? AND scheduled_date = ?', [$tpl4, date('Y-m-d', strtotime("$mon2 +2 days"))])['c'] === 2);

echo "Individualização pela frequência cardíaca\n";
[$s, $b] = $mar->req('GET', "/app/treinos/{$mw[0]['id']}");
check('Maria vê Z1 = 120–130 bpm e Z4 = 159–170 bpm', $s === 200 && str_contains($b, 'Z1 · 120–130 bpm') && str_contains($b, 'Z4 · 159–170 bpm'));
$aw = Db::all('SELECT * FROM workouts WHERE athlete_id = ? AND source_template_id = ? ORDER BY scheduled_date', [$anaId, $tpl2]);
[$s, $b] = $ana->req('GET', "/app/treinos/{$aw[0]['id']}");
check('Ana (mesmo treino) vê Z1 = 142–151 bpm', $s === 200 && str_contains($b, 'Z1 · 142–151 bpm') && !str_contains($b, '120–130'));
check('zona sem faixa configurada avisa (Ana não tem Z4)', str_contains($b, 'Z4 · zona não configurada'));
[$s, $b] = $mar->req('GET', '/app/treinos?mes=' . substr($mw[0]['scheduled_date'], 0, 7));
check('Maria vê só o Treino 1 na lista', str_contains($b, 'Treino 1 · ') && !str_contains($b, 'Treino 2 · '));

echo "Registro preserva as zonas vigentes\n";
$tue = $mw[0]['scheduled_date'];
[$s] = $mar->post("/app/treinos/{$mw[0]['id']}/registro", ['outcome' => 'completed', 'performed_on' => $tue, 'duration_minutes' => '40', 'effort' => '8'], "/app/treinos/{$mw[0]['id']}");
check('Maria registra o treino de terça', $s === 303);
$tr1->post("$mariaPage/zonas", $zp([1 => [125, 135], 2 => [136, 145], 3 => [146, 158], 4 => [159, 170], 5 => [171, 185]]), $mariaPage);
[$s, $b] = $mar->req('GET', "/app/treinos/{$mw[0]['id']}");
check('treino JÁ REALIZADO continua mostrando as zonas da época (120–130)', str_contains($b, 'Z1 · 120–130 bpm') && !str_contains($b, '125–135'));
[$s, $b] = $mar->req('GET', "/app/treinos/{$mw[1]['id']}");
check('treino ainda NÃO realizado já usa as zonas novas (125–135)', str_contains($b, 'Z1 · 125–135 bpm'));
[$s, $b] = $tr1->req('GET', "/treinador/treinos/{$mw[1]['id']}");
check('treinador vê as zonas do atleta ao editar o treino', $s === 200 && str_contains($b, 'Zonas de FC de Maria') && str_contains($b, '125–135 bpm'));

echo "FC máxima e zonas padrão (7 zonas)\n";
foreach (['abc', '99', '231'] as $bad) {
    $tr1->post("$mariaPage/zonas", ['action' => 'defaults', 'max_hr' => $bad], $mariaPage);
}
check('FC máxima inválida é recusada (zonas não mudam)', count($zonesOf($mariaId)) === 5 && Workout_maxhr($mariaId) === null);
$tr1->post("$mariaPage/zonas", ['action' => 'defaults', 'max_hr' => ''], $mariaPage);
check('calcular pelo padrão sem FC máxima é recusado', count($zonesOf($mariaId)) === 5);
[$s] = $tr1->post("$mariaPage/zonas", ['action' => 'defaults', 'max_hr' => '190'], $mariaPage);
$z = array_column($zonesOf($mariaId), null, 'zone');
check('calcular pelo padrão: 7 zonas a partir de FCmáx 190', $s === 303 && count($z) === 7);
check('Z1 = 67–73% (127–139 bpm) e Z7 = 97–100% (184–190 bpm)', $z[1]['bpm_min'] == 127 && $z[1]['bpm_max'] == 139 && $z[7]['bpm_min'] == 184 && $z[7]['bpm_max'] == 190);
check('Z4 = 85–90% (162–171 bpm) e Z6 = 95–97% (181–184 bpm)', $z[4]['bpm_min'] == 162 && $z[4]['bpm_max'] == 171 && $z[6]['bpm_min'] == 181 && $z[6]['bpm_max'] == 184);
check('FC máxima do atleta fica gravada (190)', Workout_maxhr($mariaId) === 190);
$adj = [];
foreach ($z as $n => $r) { $adj[$n] = [(string) $r['bpm_min'], (string) $r['bpm_max']]; }
$adj[1] = ['130', '140'];
[$s] = $tr1->post("$mariaPage/zonas", $zp($adj), $mariaPage);
$z2 = array_column($zonesOf($mariaId), null, 'zone');
check('treinador ajusta só a Z1 deste atleta (130–140) e mantém as demais', $s === 303 && $z2[1]['bpm_min'] == 130 && $z2[1]['bpm_max'] == 140 && $z2[2]['bpm_min'] == $z[2]['bpm_min'] && count($z2) === 7);
check('...e a FC máxima segue 190', Workout_maxhr($mariaId) === 190);
[$s, $b] = $mar->req('GET', '/app/perfil');
check('atleta vê a FC máxima e as 7 zonas (somente leitura)', str_contains($b, '190 bpm') && str_contains($b, 'Zona 7') && str_contains($b, '130–140 bpm'));
[$s, $b] = $tr1->req('GET', $mariaPage);
check('página do atleta mostra os percentuais padrão', str_contains($b, 'padrão 67–73% da FCmáx') && str_contains($b, 'Calcular pelo padrão'));
check('atleta não calcula zonas (403/419)', in_array($mar->req('POST', "/treinador/atletas/$mariaId/zonas", ['action' => 'defaults', 'max_hr' => '150'])[0], [403, 419], true));
$tr1->post("$mariaPage/zonas", $zp([1 => [125, 135], 2 => [136, 145], 3 => [146, 158], 4 => [159, 170], 5 => [171, 185]]), $mariaPage);

echo "Edição individual e independência dos modelos\n";
$longoW = $mw[2];
$ed = ['action' => 'save', 'version' => (string) $longoW['version'], 'title' => $longoW['title'], 'scheduled_date' => $longoW['scheduled_date'], 'notes' => 'Maria: objetivo meia maratona',
    'items' => [['label' => 'Contínuo', 'duration_minutes' => '100', 'distance_km' => '18', 'hr_zone' => '2']]];
[$s] = $tr1->post("/treinador/treinos/{$longoW['id']}", $ed, "/treinador/treinos/{$longoW['id']}");
check('treinador ajusta o longo SÓ da Maria (18 km)', $s === 303 && Db::one('SELECT distance_km d FROM workout_items WHERE workout_id = ?', [$longoW['id']])['d'] === '18.00');
check('o modelo continua com 12 km', Db::one('SELECT distance_km d FROM workout_template_items WHERE template_id = ? AND distance_km IS NOT NULL', [$tpl1])['d'] === '12.00');
check('o longo da Ana (15 km) não mudou', Db::one('SELECT i.distance_km d FROM workout_items i JOIN workouts w ON w.id = i.workout_id WHERE w.athlete_id = ? AND i.distance_km IS NOT NULL', [$anaId])['d'] === '15.00');
$tr1->post("/treinador/modelos/$tpl1", ['action' => 'save', 'name' => 'Treino 1', 'level_description' => 'pace acima de 6:30/km', 'sessions' => [$sess(2, 'Só um dia', $iv())]], "/treinador/modelos/$tpl1");
check('editar o modelo depois (agora 1 sessão)', (int) Db::one('SELECT COUNT(*) c FROM workout_template_sessions WHERE template_id = ?', [$tpl1])['c'] === 1);
check('...não altera as 3 sessões já entregues à Maria', (int) Db::one('SELECT COUNT(*) c FROM workouts WHERE athlete_id = ? AND source_template_id = ?', [$mariaId, $tpl1])['c'] === 3);
$tr1->post('/treinador/modelos/aplicar', $ap(), $aplicar);
check('reaplicar a mesma semana não duplica as sessões já entregues', (int) Db::one('SELECT COUNT(*) c FROM workouts WHERE athlete_id = ? AND source_template_id = ?', [$anaId, $tpl2])['c'] === 2 && (int) Db::one("SELECT COUNT(*) c FROM workouts WHERE athlete_id = ? AND title = 'Treino 1 · Intervalado velocidade 1'", [$mariaId])['c'] === 1);

echo "Atleta que já recebeu treino sai da lista de pendentes\n";
Db::run('DELETE FROM rate_events');
$carlos = new Client(); register($carlos, ['email' => 'carlos@example.com', 'name' => 'Carlos Novo']);
$carlosId = $uid('carlos@example.com');
$cli('assign-trainer.php', ['assign', 'trn@example.com', 'carlos@example.com', 'corrida']);
$wk = date('Y-m-d', strtotime('monday +3 weeks'));
$page = "/treinador/modelos/aplicar?modalidade=corrida&semana=$wk";
$inList = static fn (string $b, int $id) => str_contains($b, 'value="' . $id . '" data-athlete="' . $id . '"');
[$s, $b] = $tr1->req('GET', $page);
check('semana sem treinos: Maria, Ana e Carlos aparecem para marcar', $s === 200 && $inList($b, $mariaId) && $inList($b, $anaId) && $inList($b, $carlosId));
check('...com o contador de pendentes (3 sem treino · 0 já com treino)', str_contains($b, '3 atleta(s) sem treino nesta semana · 0 já com treino'));
$tr1->post('/treinador/modelos/aplicar', ['modality' => 'corrida', 'scheduled_date' => $wk, 'publish' => '1', 'tpl' => [$tpl4 => [$mariaId]]], $aplicar);
[$s, $b] = $tr1->req('GET', $page);
check('Maria recebeu o treino e SAIU da lista; ficam Ana e Carlos', !$inList($b, $mariaId) && $inList($b, $anaId) && $inList($b, $carlosId));
check('...e aparece em "Já com treino nesta semana" como Enviado', str_contains($b, 'Já com treino nesta semana') && str_contains($b, 'Maria Teste') && str_contains($b, 'Enviado'));
check('...contador: 2 sem treino · 1 já com treino', str_contains($b, '2 atleta(s) sem treino nesta semana · 1 já com treino'));
$weekCount = static fn (int $id) => (int) Db::one("SELECT COUNT(*) c FROM workouts WHERE athlete_id = ? AND scheduled_date BETWEEN ? AND ? AND status <> 'cancelled'", [$id, $wk, date('Y-m-d', strtotime("$wk +6 days"))])['c'];
$tr1->post('/treinador/modelos/aplicar', ['modality' => 'corrida', 'scheduled_date' => $wk, 'publish' => '1', 'tpl' => [$tpl4 => [$mariaId, $carlosId]]], $aplicar);
check('reenviar com Maria marcada (tela desatualizada): ela é ignorada, Carlos recebe', $weekCount($mariaId) === 1 && $weekCount($carlosId) === 1);
$tr1->post('/treinador/modelos/aplicar', ['modality' => 'corrida', 'scheduled_date' => $wk, 'publish' => '1', 'tpl' => [$tpl1 => [$mariaId]]], $aplicar);
check('outro treino para quem já tem a semana também é ignorado', $weekCount($mariaId) === 1);
$tr1->post('/treinador/modelos/aplicar', ['modality' => 'corrida', 'scheduled_date' => $wk, 'publish' => '0', 'tpl' => [$tpl4 => [$anaId]]], $aplicar);
[$s, $b] = $tr1->req('GET', $page);
check('quando todos já têm treino, a lista de pendentes some', str_contains($b, 'Todos os atletas já têm treino nesta semana') && !$inList($b, $anaId));
check('...rascunho aparece como Rascunho (não conta como enviado ao atleta)', str_contains($b, 'Rascunho'));
[$s, $b] = $tr1->req('GET', "/treinador/modelos/aplicar?modalidade=corrida&semana=" . date('Y-m-d', strtotime("$wk +7 days")));
check('na semana seguinte todos voltam a aparecer', $inList($b, $mariaId) && $inList($b, $anaId) && $inList($b, $carlosId));

echo "Rascunho, arquivar, musculação e triathlon\n";
$nextMon = date('Y-m-d', strtotime('monday next week'));
$tr1->post('/treinador/modelos/aplicar', $ap(['scheduled_date' => $nextMon, 'publish' => '0', 'pick' => [$anaId => (string) $tpl2]]), $aplicar);
$dr = Db::all("SELECT status FROM workouts WHERE athlete_id = ? AND scheduled_date >= ? AND source_template_id = ?", [$anaId, $nextMon, $tpl2]);
check('aplicar como rascunho cria as 2 sessões em rascunho', count($dr) === 2 && $dr[0]['status'] === 'draft');
$draftId = (int) Db::one("SELECT id FROM workouts WHERE athlete_id = ? AND status = 'draft' LIMIT 1", [$anaId])['id'];
[$s] = $ana->req('GET', "/app/treinos/$draftId");
check('rascunho aplicado não abre para o atleta (404)', $s === 404);
$tr1->post("/treinador/modelos/$tpl2", ['action' => 'archive'], "/treinador/modelos/$tpl2");
check('arquivar modelo', Db::one('SELECT archived_at a FROM workout_templates WHERE id = ?', [$tpl2])['a'] !== null);
check('...e o limpa como treino padrão dos atletas', Db::one('SELECT default_template_id d FROM trainer_athletes WHERE athlete_id = ? AND modality_id = 1', [$anaId])['d'] === null);
$before = $count();
$tr1->post('/treinador/modelos/aplicar', $ap(['scheduled_date' => $nextMon, 'pick' => [$mariaId => (string) $tpl2]]), $aplicar);
check('modelo arquivado não pode ser aplicado', $count() === $before);
check('treinos já entregues do modelo arquivado continuam com a Ana', Db::one("SELECT status FROM workouts WHERE athlete_id = ? AND source_template_id = ? AND status = 'published' LIMIT 1", [$anaId, $tpl2]) !== null);

$day = date('Y-m-d', strtotime('monday next week'));
[$s] = $tr2->post('/treinador/modelos/aplicar', ['modality' => 'musculacao', 'scheduled_date' => $day, 'publish' => '1', 'pick' => [$joaoId => (string) $mtA]], '/treinador/modelos/aplicar?modalidade=musculacao');
$jw = Db::all('SELECT id, discipline, scheduled_date FROM workouts WHERE athlete_id = ? AND source_template_id = ? ORDER BY scheduled_date', [$joaoId, $mtA]);
check('semana de musculação aplicada (segunda e quarta)', count($jw) === 2 && $jw[0]['discipline'] === 'musculacao' && $jw[0]['scheduled_date'] === $day && $jw[1]['scheduled_date'] === date('Y-m-d', strtotime("$day +2 days")));
[$s, $b] = $joa->req('GET', "/app/treinos/{$jw[1]['id']}");
check('atleta vê exercício, séries, repetições e carga da quarta', str_contains($b, 'Remada curvada') && str_contains($b, '30 kg') && str_contains($b, '60 s'));

$trForm = '/treinador/modelos/novo?modalidade=triathlon';
[$s] = $tri->post('/treinador/modelos/novo', ['modality' => 'triathlon', 'name' => 'Treino 3', 'level_description' => '', 'sessions' => [
    ['weekday' => '1', 'title' => 'Natação técnica', 'discipline' => 'natacao', 'notes' => '', 'items' => [['label' => '8x100m', 'duration_minutes' => '40', 'distance_km' => '1.5', 'hr_zone' => '2']]],
    ['weekday' => '3', 'title' => 'Pedal base', 'discipline' => 'ciclismo', 'notes' => '', 'items' => [['label' => 'Z2 contínuo', 'duration_minutes' => '90', 'distance_km' => '40', 'hr_zone' => '2']]],
    ['weekday' => '6', 'title' => 'Corrida de transição', 'discipline' => 'corrida', 'notes' => '', 'items' => [['label' => 'Brick', 'duration_minutes' => '30', 'hr_zone' => '3']]]]], $trForm);
$tri3 = (int) preg_replace('#.*/#', '', (string) $tri->header('Location'));
check('modelo semanal de triathlon com natação, ciclismo e corrida', $s === 303 && (int) Db::one('SELECT COUNT(DISTINCT discipline) c FROM workout_template_sessions WHERE template_id = ?', [$tri3])['c'] === 3);
$tri->post('/treinador/modelos/aplicar', ['modality' => 'triathlon', 'scheduled_date' => $day, 'publish' => '1', 'pick' => [$joaoId => (string) $tri3]], '/treinador/modelos/aplicar?modalidade=triathlon');
check('semana de triathlon aplicada com as 3 disciplinas', (int) Db::one("SELECT COUNT(DISTINCT discipline) c FROM workouts WHERE athlete_id = ? AND source_template_id = ?", [$joaoId, $tri3])['c'] === 3);
check('aplicações auditadas', (int) Db::one("SELECT COUNT(*) c FROM audit_log WHERE action = 'workout_from_template'")['c'] >= 10);

echo "\nResultado: $pass ok, $fail falha(s)\n";
exit($fail ? 1 : 0);
