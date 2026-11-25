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
foreach (['workout_template_items', 'workout_template_sessions', 'workout_templates', 'athlete_hr_zones', 'athlete_hr_settings', 'contract_events', 'charges', 'contracts', 'plan_prices', 'trainer_settings', 'capacity_exceptions', 'trainer_availability', 'workout_log_revisions', 'workout_logs', 'workout_versions', 'workout_items', 'workouts', 'trainer_athletes', 'modalities', 'athlete_profiles', 'audit_log', 'consents', 'password_resets', 'rate_events', 'user_roles', 'users', 'schema_migrations'] as $t) {
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

[$s, $b] = $tr1->req('GET', '/treinador/atletas');
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

echo "Modelos de treino com sessões livres\n";
$far = date('Y-m-d', strtotime('monday this week +8 weeks'));
$stepOne = "/treinador/modelos/aplicar?modalidade=corrida&semana=$far";
[$s, $b] = $tr1->req('GET', '/treinador/modelos');
check('biblioteca de modelos = 200 (vazia)', $s === 200 && str_contains($b, 'Nenhum modelo criado'));
[$s] = $mar->req('GET', '/treinador/modelos');
check('atleta não acessa modelos (403)', $s === 403);
[$s] = $tr1->req('GET', '/treinador/modelos/novo?modalidade=musculacao');
check('modelo em modalidade sem atletas = 404', $s === 404);

$tplForm = '/treinador/modelos/novo?modalidade=corrida';
$sess = static fn (string $title, array $items, array $over = []) => $over + ['title' => $title, 'discipline' => 'corrida', 'notes' => '', 'items' => $items];
$iv = static fn (string $z = '4') => [['label' => 'Aquecimento', 'duration_minutes' => '15', 'hr_zone' => '1'], ['label' => 'Tiros', 'duration_minutes' => '25', 'hr_zone' => $z, 'target' => '4:30/km']];
$longo = static fn (string $km = '12') => [['label' => 'Contínuo', 'duration_minutes' => '70', 'distance_km' => $km, 'hr_zone' => '2']];
$T = static fn (string $name, array $sessions, array $over = []) => $over + ['modality' => 'corrida', 'name' => $name, 'level_description' => 'pace acima de 6:30/km', 'sessions' => $sessions, '_end' => '1'];

[$s, $b] = $tr1->req('GET', $tplForm);
check('formulário do modelo NÃO tem dia da semana', $s === 200 && !str_contains($b, 'Dia da semana') && !str_contains($b, '[weekday]'));
foreach ([
    'nenhuma sessão' => $T('Treino X', []),
    'zona de FC 8' => $T('Treino X', [$sess('Velocidade', $iv('8'))]),
    'sessão sem linhas' => $T('Treino X', [$sess('Velocidade', [])]),
    'modelo sem nome' => $T('', [$sess('Velocidade', $iv())]),
] as $label => $data) {
    [$s] = $tr1->post('/treinador/modelos/novo', $data, $tplForm);
    check("modelo rejeita: $label (422)", $s === 422, "status=$s");
}
$cut = $T('Treino Cortado', [$sess('Velocidade', $iv())]);
unset($cut['_end']);
$tr1->post('/treinador/modelos/novo', $cut, $tplForm);
check('formulário que chega incompleto não grava nada', Db::one("SELECT id FROM workout_templates WHERE name = 'Treino Cortado'") === null);

[$s] = $tr1->post('/treinador/modelos/novo', $T('Treino 1', [$sess('Velocidade 1', $iv()), $sess('Velocidade 2', $iv('5')), $sess('', $iv('6')), $sess('Longo', $longo())]), $tplForm);
$tpl1 = (int) preg_replace('#.*/#', '', (string) $tr1->header('Location'));
check('Treino 1 criado com 4 sessões', $s === 303 && $tpl1 > 0 && (int) Db::one('SELECT COUNT(*) c FROM workout_template_sessions WHERE template_id = ?', [$tpl1])['c'] === 4);
check('sessões sem dia fixo; sessão sem título vira "Sessão 3"', Db::one('SELECT COUNT(*) c FROM workout_template_sessions WHERE template_id = ? AND weekday IS NOT NULL', [$tpl1])['c'] == 0
    && Db::one("SELECT title t FROM workout_template_sessions WHERE template_id = ? AND position = 3", [$tpl1])['t'] === 'Sessão 3');
$s1 = array_map('intval', array_column(Db::all('SELECT id FROM workout_template_sessions WHERE template_id = ? ORDER BY position', [$tpl1]), 'id'));

$many = [];
for ($i = 1; $i <= 20; $i++) { $many[] = $sess("Velocidade $i", [['label' => 'Tiro', 'duration_minutes' => '10', 'hr_zone' => '5']]); }
[$s] = $tr1->post('/treinador/modelos/novo', $T('Treino Muitas', $many), $tplForm);
$tplMany = (int) preg_replace('#.*/#', '', (string) $tr1->header('Location'));
check('número de sessões é livre (20 sessões salvas)', $s === 303 && (int) Db::one('SELECT COUNT(*) c FROM workout_template_sessions WHERE template_id = ?', [$tplMany])['c'] === 20);
$tr1->post("/treinador/modelos/$tplMany", ['action' => 'archive'], "/treinador/modelos/$tplMany");

[$s] = $tr1->post('/treinador/modelos/novo', $T('Treino 2', [$sess('Velocidade', $iv()), $sess('Longo', $longo('15'))], ['level_description' => 'pace 5:30 a 6:29/km']), $tplForm);
$tpl2 = (int) preg_replace('#.*/#', '', (string) $tr1->header('Location'));
check('Treino 2 criado', $s === 303 && $tpl2 > 0);
$s2 = array_map('intval', array_column(Db::all('SELECT id FROM workout_template_sessions WHERE template_id = ? ORDER BY position', [$tpl2]), 'id'));
[$s] = $tr2->req('GET', "/treinador/modelos/$tpl1");
check('outro treinador NÃO abre o modelo (404)', $s === 404);
[$s] = $tr2->post("/treinador/modelos/$tpl1", ['action' => 'archive'], '/treinador/modelos');
check('outro treinador NÃO arquiva o modelo (404)', $s === 404 && Db::one('SELECT archived_at a FROM workout_templates WHERE id = ?', [$tpl1])['a'] === null);

echo "Dias do atleta e passo 2 (montar a semana)\n";
$tid = $uid('trn@example.com');
$link = static fn (int $a) => Db::one('SELECT default_template_id d, train_days days FROM trainer_athletes WHERE trainer_id = ? AND athlete_id = ? AND modality_id = 1', [$tid, $a]);
$tr1->post("$mariaPage/nivel", ['modality' => 'corrida', 'template_id' => (string) $tpl1, 'days' => ['6', '1', '3', '9']], $mariaPage);
check('dias da Maria (seg, qua, sáb) e Treino 1 salvos; dia inválido ignorado', $link($mariaId)['days'] === '1,3,6' && (int) $link($mariaId)['d'] === $tpl1);
$tr1->post("/treinador/atletas/$anaId/nivel", ['modality' => 'corrida', 'template_id' => (string) $tpl1, 'days' => ['1', '4', '6']], "/treinador/atletas/$anaId");
check('dias da Ana (seg, qui, sáb)', $link($anaId)['days'] === '1,4,6');

$mon = date('Y-m-d', strtotime('monday last week'));
$dayOf = static fn (string $monday, int $d) => date('Y-m-d', strtotime("$monday +" . ($d - 1) . ' days'));
$count = static fn () => (int) Db::one('SELECT COUNT(*) c FROM workouts WHERE source_template_id IS NOT NULL')['c'];
[$s, $b] = $tr1->req('GET', $stepOne);
check('passo 1: atletas marcados pelo treino padrão; envia para o passo 2', str_contains($b, 'action="/treinador/modelos/montar"') && str_contains($b, 'value="' . $mariaId . '" data-athlete="' . $mariaId . '" checked'));
check('passo 1 não mostra atleta de outro treinador', !str_contains($b, 'João Outro'));
$before = $count();
[$s, $b] = $tr1->post('/treinador/modelos/montar', ['modality' => 'corrida', 'scheduled_date' => $mon, 'tpl' => [$tpl1 => [$mariaId, $anaId]]], $stepOne);
check('passo 2 = 200 e não grava nada', $s === 200 && str_contains($b, 'Montar a semana') && $count() === $before);
$selected = static fn (string $html, int $athlete, int $day, int $sid) => (bool) preg_match('#name="plan\[' . $athlete . '\]\[' . $day . '\]\[\]"[^>]*>(?:(?!</select>).)*<option value="' . $sid . '" selected>#s', $html);
check('passo 2 pré-monta a Maria: seg=1ª, qua=2ª, sáb=3ª sessão', $selected($b, $mariaId, 1, $s1[0]) && $selected($b, $mariaId, 3, $s1[1]) && $selected($b, $mariaId, 6, $s1[2]));
check('passo 2 pré-monta a Ana nos dias dela (quinta = 2ª sessão)', $selected($b, $anaId, 4, $s1[1]) && !$selected($b, $anaId, 3, $s1[1]));
check('passo 2 avisa que sobrou sessão sem dia', str_contains($b, '1 sessão(ões) do modelo sem dia'));
check('passo 2 oferece qualquer sessão em qualquer dia (7 dias por atleta)', substr_count($b, 'data-slot aria-label') === 14);
$tr1->post('/treinador/modelos/montar', ['modality' => 'corrida', 'scheduled_date' => $mon, 'tpl' => [$tpl1 => [$mariaId], $tpl2 => [$mariaId]]], $stepOne);
[$s, $b] = $tr1->req('GET', $stepOne);
check('mesmo atleta em dois treinos é recusado no passo 1', str_contains($b, 'mais de um treino'));

echo "Enviar a semana montada\n";
$send = static fn (array $plan, array $over = []) => $over + ['modality' => 'corrida', 'scheduled_date' => $mon, 'publish' => '1', 'plan' => $plan, '_end' => '1'];
$tr1->post('/treinador/modelos/aplicar', $send([$mariaId => [1 => (string) $s1[0]]], ['scheduled_date' => 'ontem']), $stepOne);
check('data inválida não cria nada', $count() === 0);
$tr1->post('/treinador/modelos/aplicar', $send([$mariaId => [1 => '']]), $stepOne);
check('semana toda vazia não cria nada', $count() === 0);
$tr1->post('/treinador/modelos/aplicar', $send([$mariaId => [1 => (string) $s1[0]], $joaoId => [1 => (string) $s1[0]]]), $stepOne);
check('atleta de outro treinador no envio: tudo recusado', $count() === 0);
$tr1->post('/treinador/modelos/aplicar', $send([$mariaId => [1 => '999999']]), $stepOne);
check('sessão inexistente: recusado', $count() === 0);
$tr1->post('/treinador/modelos/aplicar', $send([$mariaId => [8 => (string) $s1[0]]]), $stepOne);
check('dia inválido (8): recusado', $count() === 0);

// Maria: o LONGO na segunda; Ana: a sessão que a Maria faz na quarta vai para a quinta
[$s] = $tr1->post('/treinador/modelos/aplicar', $send([
    $mariaId => [1 => (string) $s1[3], 3 => (string) $s1[1], 6 => (string) $s1[0]],
    $anaId => [1 => (string) $s1[0], 4 => (string) $s1[1], 6 => (string) $s1[3]],
]), $stepOne);
check('semana enviada: 3 treinos para cada atleta', $s === 303 && $count() === 6, 'criados=' . $count());
$of = static fn (int $a) => Db::all('SELECT * FROM workouts WHERE athlete_id = ? AND source_template_id = ? ORDER BY scheduled_date', [$a, $tpl1]);
$mw = $of($mariaId); $aw = $of($anaId);
check('Maria: longo na SEGUNDA, velocidade na quarta e no sábado', array_column($mw, 'scheduled_date') === [$dayOf($mon, 1), $dayOf($mon, 3), $dayOf($mon, 6)] && $mw[0]['title'] === 'Treino 1 · Longo' && $mw[1]['title'] === 'Treino 1 · Velocidade 2');
check('Ana: a sessão da quarta da Maria foi para a QUINTA', $aw[1]['scheduled_date'] === $dayOf($mon, 4) && $aw[1]['title'] === 'Treino 1 · Velocidade 2');
check('linhas e zonas copiadas; histórico criado', Db::one('SELECT GROUP_CONCAT(hr_zone ORDER BY position) z FROM workout_items WHERE workout_id = ?', [$mw[1]['id']])['z'] === '1,5'
    && (int) Db::one('SELECT COUNT(*) c FROM workout_versions v JOIN workouts w ON w.id = v.workout_id WHERE w.source_template_id = ?', [$tpl1])['c'] === 6);

echo "FC individual, registro e lista de pendentes\n";
[$s, $b] = $mar->req('GET', "/app/treinos/{$mw[1]['id']}");
check('Maria vê a zona com os bpm dela (Z1 120–130)', $s === 200 && str_contains($b, 'Z1 · 120–130 bpm') && str_contains($b, 'Z5 · 171–185 bpm'));
[$s, $b] = $ana->req('GET', "/app/treinos/{$aw[1]['id']}");
check('Ana, na mesma sessão, vê os bpm dela (Z1 142–151)', str_contains($b, 'Z1 · 142–151 bpm') && !str_contains($b, '120–130'));
check('zona sem faixa configurada avisa', str_contains($b, 'Z5 · zona não configurada'));
[$s] = $mar->post("/app/treinos/{$mw[0]['id']}/registro", ['outcome' => 'completed', 'performed_on' => $mw[0]['scheduled_date'], 'duration_minutes' => '70'], "/app/treinos/{$mw[0]['id']}");
check('Maria registra o longo de segunda', $s === 303);
$tr1->post("$mariaPage/zonas", $zp([1 => [125, 135], 2 => [136, 145], 3 => [146, 158], 4 => [159, 170], 5 => [171, 185]]), $mariaPage);
[$s, $b] = $mar->req('GET', "/app/treinos/{$mw[0]['id']}");
check('treino já realizado mantém as zonas da época', str_contains($b, 'Z2 · 131–145 bpm'));
[$s, $b] = $mar->req('GET', "/app/treinos/{$mw[1]['id']}");
check('treino ainda não realizado usa as zonas novas', str_contains($b, 'Z1 · 125–135 bpm'));

[$s, $b] = $tr1->req('GET', "/treinador/modelos/aplicar?modalidade=corrida&semana=$mon");
check('quem recebeu sai da lista de pendentes', !str_contains($b, 'data-athlete="' . $mariaId . '"') && str_contains($b, 'Todos os atletas já têm treino') && str_contains($b, 'Enviado'));
$tr1->post('/treinador/modelos/aplicar', $send([$mariaId => [2 => (string) $s1[0]]]), $stepOne);
check('segundo envio na mesma semana é ignorado', $count() === 6);

$carlos = new Client(); Db::run('DELETE FROM rate_events'); register($carlos, ['email' => 'carlos@example.com', 'name' => 'Carlos Novo']);
$carlosId = $uid('carlos@example.com');
$cli('assign-trainer.php', ['assign', 'trn@example.com', 'carlos@example.com', 'corrida']);
$wk = date('Y-m-d', strtotime('monday this week +3 weeks'));
[$s, $b] = $tr1->post('/treinador/modelos/montar', ['modality' => 'corrida', 'scheduled_date' => $wk, 'tpl' => [$tpl2 => [$carlosId]]], $stepOne);
check('atleta sem dias cadastrados: grade vazia com aviso', $s === 200 && str_contains($b, 'ainda não tem dias cadastrados') && !str_contains($b, '" selected>'));
$tr1->post('/treinador/modelos/aplicar', $send([$carlosId => [2 => (string) $s2[0], 5 => (string) $s2[0], 7 => (string) $s2[1]]], ['scheduled_date' => $wk]), $stepOne);
check('a mesma sessão pode ser usada em dois dias', (int) Db::one("SELECT COUNT(*) c FROM workouts WHERE athlete_id = ? AND title = 'Treino 2 · Velocidade'", [$carlosId])['c'] === 2);
check('os dias usados são lembrados (ter, sex, dom)', $link($carlosId)['days'] === '2,5,7');
$tr1->post('/treinador/modelos/aplicar', $send([$mariaId => [1 => (string) $s2[0]]], ['scheduled_date' => $wk]), $stepOne);
check('envio não sobrescreve os dias que o atleta já tinha', $link($mariaId)['days'] === '1,3,6');

echo "Edição individual, rascunho e arquivar\n";
$w2 = $mw[2];
[$s] = $tr1->post("/treinador/treinos/{$w2['id']}", ['action' => 'save', 'version' => (string) $w2['version'], 'title' => $w2['title'], 'scheduled_date' => $w2['scheduled_date'], 'notes' => 'ajuste só da Maria',
    'items' => [['label' => 'Tiros 4x400m', 'duration_minutes' => '20', 'hr_zone' => '4']]], "/treinador/treinos/{$w2['id']}");
check('treinador ajusta o treino de um atleta só', $s === 303 && (int) Db::one('SELECT COUNT(*) c FROM workout_items WHERE workout_id = ?', [$w2['id']])['c'] === 1);
check('modelo e treino da Ana intactos', (int) Db::one('SELECT COUNT(*) c FROM workout_template_items WHERE session_id = ?', [$s1[0]])['c'] === 2
    && (int) Db::one('SELECT COUNT(*) c FROM workout_items WHERE workout_id = ?', [$aw[0]['id']])['c'] === 2);
$tr1->post("/treinador/modelos/$tpl1", ['action' => 'save', 'name' => 'Treino 1', 'level_description' => '', '_end' => '1', 'sessions' => [$sess('Só uma', $iv())]], "/treinador/modelos/$tpl1");
check('editar o modelo depois não altera o que já foi entregue', count($of($mariaId)) === 3);
$tr1->post('/treinador/modelos/aplicar', $send([$anaId => [1 => (string) $s1[0]]], ['scheduled_date' => $wk]), $stepOne);
check('sessão de uma versão antiga do modelo é recusada', (int) Db::one('SELECT COUNT(*) c FROM workouts WHERE athlete_id = ? AND scheduled_date >= ?', [$anaId, $wk])['c'] === 0);
$draftWeek = date('Y-m-d', strtotime('monday this week +5 weeks'));
$tr1->post('/treinador/modelos/aplicar', $send([$anaId => [1 => (string) $s2[0]]], ['scheduled_date' => $draftWeek, 'publish' => '0']), $stepOne);
$draft = Db::one('SELECT id, status FROM workouts WHERE athlete_id = ? AND scheduled_date = ?', [$anaId, $draftWeek]);
[$s] = $ana->req('GET', "/app/treinos/{$draft['id']}");
check('envio como rascunho não aparece para o atleta', $draft['status'] === 'draft' && $s === 404);
$tr1->post("/treinador/modelos/$tpl2", ['action' => 'archive'], "/treinador/modelos/$tpl2");
$before = $count();
$tr1->post('/treinador/modelos/aplicar', $send([$mariaId => [1 => (string) $s2[0]]], ['scheduled_date' => date('Y-m-d', strtotime('monday this week +6 weeks'))]), $stepOne);
check('modelo arquivado não pode ser enviado', $count() === $before);

echo "Musculação e triathlon\n";
$mf = '/treinador/modelos/novo?modalidade=musculacao';
[$s] = $tr2->post('/treinador/modelos/novo', ['modality' => 'musculacao', 'name' => 'Treino A', 'level_description' => '', '_end' => '1', 'sessions' => [
    ['title' => 'Peito', 'notes' => '', 'items' => [['label' => 'Supino reto', 'sets' => '4', 'reps' => '8-10', 'load_text' => '40 kg', 'rest_seconds' => '90']]],
    ['title' => 'Costas', 'notes' => '', 'items' => [['label' => 'Remada curvada', 'sets' => '3', 'reps' => '12', 'load_text' => '30 kg', 'rest_seconds' => '60']]]]], $mf);
$mt = (int) preg_replace('#.*/#', '', (string) $tr2->header('Location'));
$ms = array_map('intval', array_column(Db::all('SELECT id FROM workout_template_sessions WHERE template_id = ? ORDER BY position', [$mt]), 'id'));
$tr1->post('/treinador/modelos/aplicar', $send([$mariaId => [1 => (string) ($ms[0] ?? 0)]], ['scheduled_date' => $wk]), $stepOne);
check('treinador não envia sessão de modelo de OUTRO treinador', (int) Db::one('SELECT COUNT(*) c FROM workouts WHERE source_template_id = ?', [$mt])['c'] === 0);
$tr2->post('/treinador/modelos/aplicar', ['_end' => '1', 'modality' => 'musculacao', 'scheduled_date' => $wk, 'publish' => '1', 'plan' => [$joaoId => [2 => (string) $ms[1], 5 => (string) $ms[0]]]], '/treinador/modelos/aplicar?modalidade=musculacao&semana=' . $far);
$jw = Db::all('SELECT id, scheduled_date, title, discipline FROM workouts WHERE athlete_id = ? AND source_template_id = ? ORDER BY scheduled_date', [$joaoId, $mt]);
check('musculação: ordem escolhida (Costas na terça, Peito na sexta)', count($jw) === 2 && $jw[0]['title'] === 'Treino A · Costas' && $jw[0]['scheduled_date'] === $dayOf($wk, 2) && $jw[1]['scheduled_date'] === $dayOf($wk, 5));
[$s, $b] = $joa->req('GET', "/app/treinos/{$jw[0]['id']}");
check('atleta vê séries, repetições e carga', str_contains($b, 'Remada curvada') && str_contains($b, '30 kg') && str_contains($b, '60 s'));

$trf = '/treinador/modelos/novo?modalidade=triathlon';
[$s] = $tri->post('/treinador/modelos/novo', ['modality' => 'triathlon', 'name' => 'Treino 3', 'level_description' => '', '_end' => '1', 'sessions' => [
    ['title' => 'Natação', 'discipline' => 'natacao', 'notes' => '', 'items' => [['label' => '8x100m', 'duration_minutes' => '40', 'hr_zone' => '2']]],
    ['title' => 'Pedal', 'discipline' => 'ciclismo', 'notes' => '', 'items' => [['label' => 'Base', 'duration_minutes' => '90', 'hr_zone' => '2']]],
    ['title' => 'Corrida', 'discipline' => 'corrida', 'notes' => '', 'items' => [['label' => 'Brick', 'duration_minutes' => '30', 'hr_zone' => '3']]]]], $trf);
$tri3 = (int) preg_replace('#.*/#', '', (string) $tri->header('Location'));
$ts = array_map('intval', array_column(Db::all('SELECT id FROM workout_template_sessions WHERE template_id = ? ORDER BY position', [$tri3]), 'id'));
$tri->post('/treinador/modelos/aplicar', ['_end' => '1', 'modality' => 'triathlon', 'scheduled_date' => $wk, 'publish' => '1', 'plan' => [$joaoId => [1 => (string) $ts[0], 3 => (string) $ts[1], 6 => (string) $ts[2]]]], '/treinador/modelos/aplicar?modalidade=triathlon&semana=' . $far);
check('semana de triathlon com natação, ciclismo e corrida', (int) Db::one('SELECT COUNT(DISTINCT discipline) c FROM workouts WHERE athlete_id = ? AND source_template_id = ?', [$joaoId, $tri3])['c'] === 3);

echo "Vários treinos no mesmo dia, em qualquer combinação\n";
$cli('assign-trainer.php', ['assign', 'trn@example.com', 'maria@example.com', 'musculacao']);
$tr1->post('/treinador/modelos/novo', $T('Treino 9', [$sess('Tiro manhã', $iv()), $sess('Rodagem tarde', $longo('8'))]), $tplForm);
$t9 = (int) preg_replace('#.*/#', '', (string) $tr1->header('Location'));
$r = array_map('intval', array_column(Db::all('SELECT id FROM workout_template_sessions WHERE template_id = ? ORDER BY position', [$t9]), 'id'));
$tr1->post('/treinador/modelos/novo', ['modality' => 'musculacao', 'name' => 'Força', 'level_description' => '', '_end' => '1', 'sessions' => [
    ['title' => 'Pernas', 'notes' => '', 'items' => [['label' => 'Agachamento', 'sets' => '4', 'reps' => '10']]]]], '/treinador/modelos/novo?modalidade=musculacao');
$tm = (int) preg_replace('#.*/#', '', (string) $tr1->header('Location'));
$mm = (int) Db::one('SELECT id FROM workout_template_sessions WHERE template_id = ?', [$tm])['id'];
$w10 = date('Y-m-d', strtotime('monday this week +10 weeks'));
[$s, $b] = $tr1->post('/treinador/modelos/montar', ['modality' => 'corrida', 'scheduled_date' => $w10, 'tpl' => [$t9 => [$mariaId, $anaId]]], $stepOne);
$gridOf = static function (string $html, int $id): string { preg_match('#name="plan\[' . $id . '\]\[1\]\[\]".*?</select>#s', $html, $m); return $m[0] ?? ''; };
check('passo 2: quem tem corrida e musculação vê as duas modalidades', str_contains($gridOf($b, $mariaId), 'value="' . $mm . '"') && str_contains($gridOf($b, $mariaId), 'value="' . $r[0] . '"'));
check('passo 2: quem só tem corrida NÃO vê sessões de musculação', !str_contains($gridOf($b, $anaId), 'value="' . $mm . '"'));
check('passo 2 tem o botão "+ outro treino" em cada dia', substr_count($b, 'data-add-slot') === 14);
$before = $count();
$tr1->post('/treinador/modelos/aplicar', $send([$anaId => [1 => [(string) $r[0], (string) $mm]]], ['scheduled_date' => $w10]), $stepOne);
check('misturar modalidade sem vínculo: tudo recusado', $count() === $before);
$tr1->post('/treinador/modelos/aplicar', $send([$mariaId => [1 => array_fill(0, 11, (string) $r[0])]], ['scheduled_date' => $w10]), $stepOne);
check('mais de 10 treinos no mesmo dia: recusado', $count() === $before);
$tr1->post('/treinador/modelos/aplicar', ['modality' => 'corrida', 'scheduled_date' => $w10, 'publish' => '1', 'plan' => [$mariaId => [1 => [(string) $r[0]]]]], $stepOne);
check('montagem que chega incompleta não cria nada', $count() === $before);
[$s] = $tr1->post('/treinador/modelos/aplicar', $send([
    $mariaId => [1 => [(string) $r[0], (string) $r[1], (string) $mm], 3 => [(string) $r[0], (string) $r[0], '']],
    $anaId => [1 => [(string) $r[0], (string) $r[1]]],
], ['scheduled_date' => $w10]), $stepOne);
check('envia vários treinos no mesmo dia (5 para Maria, 2 para Ana)', $s === 303 && $count() === $before + 7, 'criados=' . ($count() - $before));
$d1 = Db::all('SELECT w.title, m.slug FROM workouts w JOIN modalities m ON m.id = w.modality_id WHERE w.athlete_id = ? AND w.scheduled_date = ? ORDER BY w.id', [$mariaId, $w10]);
check('segunda: 2 de corrida + 1 de musculação, na ordem escolhida', array_column($d1, 'title') === ['Treino 9 · Tiro manhã', 'Treino 9 · Rodagem tarde', 'Força · Pernas'] && array_column($d1, 'slug') === ['corrida', 'corrida', 'musculacao']);
check('a mesma sessão duas vezes no mesmo dia', (int) Db::one('SELECT COUNT(*) c FROM workouts WHERE athlete_id = ? AND scheduled_date = ?', [$mariaId, date('Y-m-d', strtotime("$w10 +2 days"))])['c'] === 2);
[$s, $b] = $mar->req('GET', '/app/treinos?mes=' . substr($w10, 0, 7));
check('atleta vê todos os treinos do dia', str_contains($b, 'Tiro manhã') && str_contains($b, 'Rodagem tarde') && str_contains($b, 'Força · Pernas'));
$tri->post('/treinador/modelos/aplicar', ['_end' => '1', 'modality' => 'triathlon', 'scheduled_date' => $w10, 'publish' => '1', 'plan' => [$joaoId => [2 => [(string) $ts[0], (string) $ts[1], (string) $ts[2]], 4 => [(string) $ts[0], (string) $ts[0]]]]], '/treinador/modelos/aplicar?modalidade=triathlon&semana=' . $far);
$d2 = array_column(Db::all('SELECT discipline FROM workouts WHERE athlete_id = ? AND scheduled_date = ? ORDER BY id', [$joaoId, date('Y-m-d', strtotime("$w10 +1 day"))]), 'discipline');
check('natação, ciclismo e corrida no mesmo dia', $d2 === ['natacao', 'ciclismo', 'corrida']);
check('natação e natação no mesmo dia', (int) Db::one("SELECT COUNT(*) c FROM workouts WHERE athlete_id = ? AND scheduled_date = ? AND discipline = 'natacao'", [$joaoId, date('Y-m-d', strtotime("$w10 +3 days"))])['c'] === 2);

echo "Excluir treino pela tela de aplicar\n";
$wkPage = "/treinador/modelos/aplicar?modalidade=corrida&semana=$w10";
$del = static fn (int $athlete, string $week) => ['modality' => 'corrida', 'scheduled_date' => $week, 'athlete_id' => (string) $athlete];
$active = static fn (int $a, string $week, string $mod) => (int) Db::one("SELECT COUNT(*) c FROM workouts w JOIN modalities m ON m.id = w.modality_id WHERE w.athlete_id = ? AND m.slug = ? AND w.status <> 'cancelled' AND w.scheduled_date BETWEEN ? AND ?", [$a, $mod, $week, date('Y-m-d', strtotime("$week +6 days"))])['c'];
$totalBefore = (int) Db::one('SELECT COUNT(*) c FROM workouts')['c'];
[$s, $b] = $tr1->req('GET', $wkPage);
check('"Já com treino" tem o botão Excluir treino', $s === 200 && str_contains($b, 'aria-label="Excluir treino de Maria Teste"') && !str_contains($b, 'data-athlete="' . $mariaId . '"'));
[$s] = $mar->req('POST', '/treinador/modelos/excluir', $del($mariaId, $w10));
check('atleta não exclui treino (403/419)', in_array($s, [403, 419], true) && $active($mariaId, $w10, 'corrida') === 4);
[$s] = $tr2->post('/treinador/modelos/excluir', ['modality' => 'musculacao'] + $del($mariaId, $w10), '/treinador/modelos');
check('treinador sem vínculo não exclui (404)', $s === 404 && $active($mariaId, $w10, 'musculacao') === 1);
[$s] = $anon->req('POST', '/treinador/modelos/excluir', $del($mariaId, $w10));
check('excluir sem CSRF = 419', $s === 419);
[$s] = $tr1->post('/treinador/modelos/excluir', $del($mariaId, $w10), $stepOne);
check('excluir os treinos da semana (4 de corrida cancelados)', $s === 303 && $active($mariaId, $w10, 'corrida') === 0);
check('nada é apagado: os treinos ficam como Cancelado, com histórico', (int) Db::one('SELECT COUNT(*) c FROM workouts')['c'] === $totalBefore
    && (int) Db::one("SELECT COUNT(*) c FROM workout_versions v JOIN workouts w ON w.id = v.workout_id WHERE w.athlete_id = ? AND v.change_note = 'cancelled' AND w.scheduled_date >= ?", [$mariaId, $w10])['c'] === 4);
check('treino de outra modalidade (musculação) não é tocado', $active($mariaId, $w10, 'musculacao') === 1);
[$s, $b] = $tr1->req('GET', $wkPage);
check('o nome VOLTA para a lista inicial', str_contains($b, 'data-athlete="' . $mariaId . '"') && !str_contains($b, 'aria-label="Excluir treino de Maria Teste"'));
[$s, $b] = $mar->req('GET', '/app/treinos?mes=' . substr($w10, 0, 7));
check('os treinos excluídos somem para o atleta', !str_contains($b, 'Tiro manhã') && str_contains($b, 'Força · Pernas'));
$tr1->post('/treinador/modelos/aplicar', $send([$mariaId => [2 => [(string) $r[0]]]], ['scheduled_date' => $w10]), $stepOne);
check('depois de excluir dá para enviar de novo', $active($mariaId, $w10, 'corrida') === 1);

$logged = (int) Db::one('SELECT COUNT(*) c FROM workout_logs l JOIN workouts w ON w.id = l.workout_id WHERE w.athlete_id = ? AND w.scheduled_date BETWEEN ? AND ?', [$mariaId, $mon, date('Y-m-d', strtotime("$mon +6 days"))])['c'];
$tr1->post('/treinador/modelos/excluir', $del($mariaId, $mon), $stepOne);
check('treino já registrado pelo atleta é mantido', $logged >= 1 && $active($mariaId, $mon, 'corrida') === $logged);
[$s, $b] = $tr1->req('GET', "/treinador/modelos/aplicar?modalidade=corrida&semana=$mon");
check('...e a tela avisa que há treino registrado (sem botão se não sobrou nada a excluir)', str_contains($b, 'já registrado(s)') && !str_contains($b, 'aria-label="Excluir treino de Maria Teste"'));

echo "Tela geral de Zonas de FC\n";
[$s, $b] = $tr1->req('GET', '/treinador/zonas');
check('lista todos os atletas do treinador com as 7 zonas', $s === 200 && str_contains($b, 'Maria Teste') && str_contains($b, 'Ana Corredora') && str_contains($b, 'Carlos Novo') && str_contains($b, 'z[' . $anaId . '][zones][7][min]'));
check('não lista atleta de outro treinador', !str_contains($b, 'João Outro'));
[$s] = $mar->req('GET', '/treinador/zonas');
check('atleta não acessa a tela de zonas (403)', $s === 403);
$row = static function (int $id, array $over = []) use ($zonesOf) {
    $r = ['max' => '', 'zones' => []];
    foreach ($zonesOf($id) as $z) { $r['zones'][$z['zone']] = ['min' => (string) $z['bpm_min'], 'max' => (string) $z['bpm_max']]; }
    return array_replace_recursive($r, $over);
};
$stamp = static fn (int $id) => Db::all('SELECT zone, bpm_min, bpm_max, updated_at FROM athlete_hr_zones WHERE athlete_id = ? ORDER BY zone', [$id]);
$mariaBefore = $stamp($mariaId);
[$s] = $tr1->post('/treinador/zonas', ['_end' => '1', 'z' => [$mariaId => $row($mariaId), $anaId => $row($anaId, ['zones' => [3 => ['min' => '161', 'max' => '168']]])]], '/treinador/zonas');
$anaZ = array_column($zonesOf($anaId), null, 'zone');
check('ajusta a zona de UM atleta pela tabela', $s === 303 && $anaZ[3]['bpm_min'] == 161 && $anaZ[3]['bpm_max'] == 168);
check('linhas sem mudança não são regravadas', $stamp($mariaId) === $mariaBefore);
[$s] = $tr1->post('/treinador/zonas', ['_end' => '1', 'z' => [$mariaId => $row($mariaId, ['zones' => [1 => ['min' => '100', 'max' => '110']]]), $anaId => $row($anaId, ['zones' => [1 => ['min' => '200', 'max' => '150']]])]], '/treinador/zonas');
check('uma linha inválida: nada é salvo (422)', $s === 422 && $stamp($mariaId) === $mariaBefore);
$tr1->post('/treinador/zonas', ['z' => [$mariaId => $row($mariaId, ['zones' => [1 => ['min' => '100', 'max' => '110']]])]], '/treinador/zonas');
check('tabela que chega incompleta não grava', $stamp($mariaId) === $mariaBefore);
[$s] = $tr1->post('/treinador/zonas', ['_end' => '1', 'calc' => (string) $carlosId, 'z' => [$carlosId => ['max' => '180'], $anaId => $row($anaId)]], '/treinador/zonas');
$cz = array_column($zonesOf($carlosId), null, 'zone');
check('botão Calcular preenche as 7 zonas do atleta (FCmáx 180)', $s === 303 && count($cz) === 7 && $cz[1]['bpm_min'] == 121 && $cz[7]['bpm_max'] == 180 && Workout_maxhr($carlosId) === 180);
[$s] = $tr1->post('/treinador/zonas', ['_end' => '1', 'z' => [$joaoId => ['max' => '190']]], '/treinador/zonas');
check('não altera atleta não vinculado (404)', $s === 404 && count($zonesOf($joaoId)) === 0);
[$s, $b] = $ana->req('GET', '/app/perfil');
check('o atleta recebe a própria FC atualizada', str_contains($b, '161–168 bpm'));
check('envios auditados', (int) Db::one("SELECT COUNT(*) c FROM audit_log WHERE action = 'workout_from_template'")['c'] >= 10);

echo "Sprint 4 — painel do treinador\n";
Db::run('DELETE FROM rate_events');
foreach (['/treinador', '/treinador/atletas', '/treinador/acompanhamento', '/treinador/agenda', '/treinador/disponibilidade', '/treinador/novidades'] as $path) {
    [$s] = $tr1->req('GET', $path);
    [$s2] = $mar->req('GET', $path);
    check("$path: treinador 200, atleta 403", $s === 200 && $s2 === 403, "t=$s a=$s2");
}
$tid = $uid('trn@example.com');
$activeNow = (int) Db::one("SELECT COUNT(DISTINCT athlete_id) c FROM trainer_athletes WHERE trainer_id = ? AND status = 'active'", [$tid])['c'];
[$s, $b] = $tr1->req('GET', '/treinador');
check('início mostra capacidade (padrão 30)', str_contains($b, '/ 30 atletas') && str_contains($b, '<p class="stat">' . $activeNow . '<small>'));
check('presencial separado e sem número inventado', str_contains($b, 'Presencial (Personal Trainer): ainda não contabilizado'));

echo "Capacidade: alerta, bloqueio e exceção\n";
$cli('set-capacity.php', ['trn@example.com', (string) ($activeNow + 5)]);
[$s, $b] = $tr1->req('GET', '/treinador');
check('abaixo de 80%: sem aviso', !str_contains($b, 'da sua capacidade') && !str_contains($b, 'Capacidade esgotada'));
$cli('set-capacity.php', ['trn@example.com', (string) $activeNow]);
[$s, $b] = $tr1->req('GET', '/treinador');
check('em 100%: aviso de capacidade esgotada', str_contains($b, 'Capacidade esgotada'));
$dani = new Client(); register($dani, ['email' => 'dani@example.com', 'name' => 'Dani Nova']);
$daniId = $uid('dani@example.com');
$linked = static fn (int $a) => (int) Db::one("SELECT COUNT(*) c FROM trainer_athletes WHERE trainer_id = ? AND athlete_id = ? AND status = 'active'", [$tid, $a])['c'];
check('no limite, nova atribuição é BLOQUEADA', $cli('assign-trainer.php', ['assign', 'trn@example.com', 'dani@example.com', 'corrida']) === 2 && $linked($daniId) === 0);
check('bloqueio auditado', Db::one("SELECT id FROM audit_log WHERE action = 'capacity_blocked'") !== null);
check('outra modalidade de quem JÁ é atleta não consome vaga', $cli('assign-trainer.php', ['assign', 'trn@example.com', 'ana@example.com', 'musculacao']) === 0 && $linked($anaId) === 2);
check('exceção com motivo é permitida e registrada', $cli('assign-trainer.php', ['assign', 'trn@example.com', 'dani@example.com', 'corrida', '--excecao=Autorizado pela administração']) === 0
    && $linked($daniId) === 1 && Db::one('SELECT reason FROM capacity_exceptions WHERE athlete_id = ?', [$daniId])['reason'] === 'Autorizado pela administração');
$cli('set-capacity.php', ['trn@example.com', '5']);
[$s, $b] = $tr1->req('GET', '/treinador');
check('4 de 5 atletas (80%): alerta no painel', Correon\Capacity::status($tid)['state'] === 'alert' && str_contains($b, 'você já usa 80% da sua capacidade'), json_encode(Correon\Capacity::status($tid)));
check('capacidade inválida é recusada pelo script', $cli('set-capacity.php', ['trn@example.com', '0']) !== 0);
$cli('set-capacity.php', ['trn@example.com', '30']);

echo "Perfil do atleta para o treinador\n";
[$s, $b] = $tr1->req('GET', $mariaPage);
check('treinador vinculado vê objetivo, cidade e telefone', str_contains($b, 'Santos') && str_contains($b, '11912345678') && str_contains($b, 'Correr 10 km'));
check('mostra idade, não a data de nascimento; HTML escapado', str_contains($b, ' anos</dd>') && !str_contains($b, '1990-05-20') && !str_contains($b, '20/05/1990') && !str_contains($b, '<b>sem parar</b>'));
check('acesso ao perfil é auditado', Db::one("SELECT id FROM audit_log WHERE action = 'athlete_profile_viewed' AND user_id = ? AND entity_id = ?", [$tid, $mariaId]) !== null);
[$s, $b] = $tr2->req('GET', $mariaPage);
check('treinador sem vínculo não vê o perfil (404)', $s === 404 && !str_contains($b, 'Santos'));
[$s, $b] = $mar->req('GET', '/app/perfil');
check('atleta é avisado de que o treinador vê o perfil', str_contains($b, 'treinadores vinculados a você podem ver'));
check('resumo das últimas 4 semanas na página do atleta', str_contains($tr1->req('GET', $mariaPage)[1], 'Últimas 4 semanas'));

echo "Acompanhamento, agenda e novidades\n";
$today = date('Y-m-d');
$tr1->post('/treinador/treinos/novo', ['athlete_id' => $mariaId, 'modality' => 'corrida', 'title' => 'Treino de hoje', 'scheduled_date' => $today, 'notes' => '', 'action' => 'publish',
    'items' => [['label' => 'Rodagem', 'duration_minutes' => '30', 'hr_zone' => '2']]], "/treinador/treinos/novo?atleta=$mariaId&modalidade=corrida");
$hoje = (int) preg_replace('#.*/#', '', (string) $tr1->header('Location'));
$tr1->req('GET', '/treinador/novidades');
sleep(1);
$mar->post("/app/treinos/$hoje/registro", ['outcome' => 'completed', 'performed_on' => $today, 'effort' => '9', 'notes' => 'Muito <i>puxado</i>'], "/app/treinos/$hoje");
check('registro novo conta como novidade', Correon\TrainerPanel::unseen($tid) === 1);
[$s, $b] = $tr1->req('GET', '/treinador');
check('menu mostra "Novidades (1)"', str_contains($b, 'Novidades (1)'));
[$s, $b] = $tr1->req('GET', '/treinador/novidades');
check('novidades mostra o registro como Novo, com comentário escapado', str_contains($b, 'Treino de hoje') && str_contains($b, '>Novo<') && str_contains($b, '&lt;i&gt;puxado') && !str_contains($b, '<i>puxado</i>'));
check('depois de visto, o contador zera', Correon\TrainerPanel::unseen($tid) === 0);
[$s, $b] = $tr2->req('GET', '/treinador/novidades');
check('outro treinador não vê o registro', !str_contains($b, 'Treino de hoje'));
[$s, $b] = $tr1->req('GET', '/treinador/acompanhamento?semanas=4');
check('acompanhamento lista meus atletas e registros', $s === 200 && str_contains($b, 'Maria Teste') && str_contains($b, 'Treino de hoje') && !str_contains($b, 'João Outro'));
[$s] = $tr1->req('GET', '/treinador/acompanhamento?semanas=999');
check('período inválido cai no padrão (200)', $s === 200);
[$s, $b] = $tr1->req('GET', '/treinador/agenda');
check('agenda da semana mostra o treino de hoje', $s === 200 && str_contains($b, 'Treino de hoje') && str_contains($b, 'Maria Teste'));
[$s, $b] = $tr2->req('GET', '/treinador/agenda');
check('agenda de outro treinador não mostra meus atletas', !str_contains($b, 'Maria Teste'));
[$s, $b] = $tr1->req('GET', '/treinador/agenda?semana=abc');
check('semana inválida na agenda não quebra', $s === 200);

echo "Disponibilidade\n";
$slot = static fn (array $o = []) => $o + ['weekday' => '1', 'start_time' => '06:00', 'end_time' => '09:00', 'mode' => 'presencial'];
$slots = static fn () => (int) Db::one('SELECT COUNT(*) c FROM trainer_availability WHERE trainer_id = ?', [$tid])['c'];
[$s] = $tr1->post('/treinador/disponibilidade', $slot());
check('adiciona horário', $s === 303 && $slots() === 1);
foreach (['fim antes do início' => ['weekday' => '2', 'start_time' => '10:00', 'end_time' => '09:00'], 'sobreposição' => ['start_time' => '08:00', 'end_time' => '10:00'],
    'dia inválido' => ['weekday' => '9'], 'hora inválida' => ['weekday' => '3', 'start_time' => '25:00'], 'tipo inválido' => ['weekday' => '3', 'mode' => 'x']] as $label => $over) {
    [$s] = $tr1->post('/treinador/disponibilidade', $slot($over));
    check("horário rejeitado: $label (422)", $s === 422 && $slots() === 1, "status=$s");
}
[$s] = $tr1->post('/treinador/disponibilidade', $slot(['start_time' => '09:00', 'end_time' => '11:00', 'mode' => 'online']));
check('horário encostado (sem sobrepor) é aceito', $s === 303 && $slots() === 2);
$sid = (int) Db::one('SELECT id FROM trainer_availability WHERE trainer_id = ? LIMIT 1', [$tid])['id'];
[$s] = $tr2->post("/treinador/disponibilidade/$sid/remover", [], '/treinador/disponibilidade');
check('outro treinador não remove meu horário (404)', $s === 404 && $slots() === 2);
[$s] = $tr1->post("/treinador/disponibilidade/$sid/remover", [], '/treinador/disponibilidade');
check('remove o próprio horário', $s === 303 && $slots() === 1);

echo "Sprint 5 — painel administrativo\n";
Db::run('DELETE FROM rate_events');
$adm = new Client(); $adm->post('/entrar', ['email' => 'adm@example.com', 'password' => 'senha-staff-12345']);
$admId = $uid('adm@example.com');
foreach (['/admin', '/admin/atletas', '/admin/treinadores', '/admin/relatorios', '/admin/auditoria', "/admin/atletas/$mariaId"] as $path) {
    [$s] = $adm->req('GET', $path);
    [$s2] = $tr1->req('GET', $path);
    [$s3] = $mar->req('GET', $path);
    [$s4] = $anon->req('GET', $path);
    check(preg_replace('/\d+/', '{id}', $path) . ': admin 200; treinador e atleta 403; anônimo vai ao login', $s === 200 && $s2 === 403 && $s3 === 403 && $s4 === 303, "$s $s2 $s3 $s4");
}
$totalAth = (int) Db::one("SELECT COUNT(*) c FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.slug = 'athlete'")['c'];
[$s, $b] = $adm->req('GET', '/admin');
check('início com contagem real de usuários', str_contains($b, "Atleta: <strong>$totalAth</strong>") && str_contains($b, 'Capacidade dos treinadores'));
[$s, $b] = $adm->req('GET', '/admin/atletas?q=maria');
check('busca de atletas por nome', str_contains($b, 'Maria Teste') && !str_contains($b, 'Ana Corredora'));
[$s, $b] = $adm->req('GET', '/admin/atletas?q=' . urlencode("%' OR '1'='1"));
check('busca com caracteres especiais não vaza nem quebra', $s === 200 && str_contains($b, '0 atleta(s)'));

echo "Atribuição pela tela\n";
$eva = new Client(); register($eva, ['email' => 'eva@example.com', 'name' => 'Eva Livre']);
$evaId = $uid('eva@example.com'); $trnId = $uid('trn@example.com');
[$s, $b] = $adm->req('GET', '/admin/atletas?sem_treinador=1');
check('filtro "sem treinador" mostra a nova atleta e não a Maria', str_contains($b, 'Eva Livre') && !str_contains($b, 'Maria Teste'));
$evaPage = "/admin/atletas/$evaId";
$linkedE = static fn () => (int) Db::one("SELECT COUNT(*) c FROM trainer_athletes WHERE athlete_id = ? AND status = 'active'", [$evaId])['c'];
$adm->post("$evaPage/vinculos", ['trainer_id' => (string) $mariaId, 'modality' => 'corrida'], $evaPage);
check('não aceita atleta como treinador', $linkedE() === 0);
$adm->post("$evaPage/vinculos", ['trainer_id' => (string) $trnId, 'modality' => 'natacao'], $evaPage);
check('não aceita modalidade inexistente', $linkedE() === 0);
[$s] = $tr1->post("$evaPage/vinculos", ['trainer_id' => (string) $trnId, 'modality' => 'corrida'], '/treinador');
check('treinador não atribui atletas (403)', $s === 403 && $linkedE() === 0);
$cap = Correon\Capacity::active($trnId);
$adm->post("/admin/treinadores/$trnId/capacidade", ['online_limit' => (string) $cap, 'presencial_limit' => ''], "/admin/treinadores/$trnId");
check('admin define o limite do treinador', Correon\Capacity::limit($trnId) === $cap);
$adm->post("$evaPage/vinculos", ['trainer_id' => (string) $trnId, 'modality' => 'corrida'], $evaPage);
[$s, $b] = $adm->req('GET', $evaPage);
check('no limite: bloqueado com explicação', $linkedE() === 0 && str_contains($b, 'Bloqueado'));
$adm->post("$evaPage/vinculos", ['trainer_id' => (string) $trnId, 'modality' => 'corrida', 'reason' => 'Aluna antiga <b>x</b>'], $evaPage);
check('com motivo: atribuído e exceção registrada', $linkedE() === 1 && Db::one('SELECT id FROM capacity_exceptions WHERE athlete_id = ?', [$evaId]) !== null);
check('auditoria registra QUAL admin atribuiu', (int) Db::one("SELECT user_id FROM audit_log WHERE action = 'trainer_assigned' AND entity_id = ? ORDER BY id DESC LIMIT 1", [$evaId])['user_id'] === $admId);
[$s, $b] = $adm->req('GET', "/admin/treinadores/$trnId");
check('página do treinador mostra a exceção (texto escapado)', str_contains($b, 'Aluna antiga &lt;b&gt;') && !str_contains($b, '<b>x</b>'));
foreach (['0', '1001', 'abc'] as $bad) {
    $adm->post("/admin/treinadores/$trnId/capacidade", ['online_limit' => $bad, 'presencial_limit' => ''], "/admin/treinadores/$trnId");
}
check('limite inválido é recusado', Correon\Capacity::limit($trnId) === $cap);
$adm->post("/admin/treinadores/$trnId/capacidade", ['online_limit' => '30', 'presencial_limit' => '8'], "/admin/treinadores/$trnId");
check('limite presencial separado', (int) Db::one('SELECT presencial_limit p FROM trainer_settings WHERE trainer_id = ?', [$trnId])['p'] === 8);
[$s] = $tr1->req('GET', "/treinador/atletas/$evaId");
check('o treinador passa a ver a atleta atribuída', $s === 200);
$adm->post("$evaPage/vinculos/encerrar", ['trainer_id' => (string) $trnId, 'modality' => 'corrida'], $evaPage);
[$s] = $tr1->req('GET', "/treinador/atletas/$evaId");
check('encerrar o vínculo tira o acesso e mantém o histórico', $linkedE() === 0 && $s === 404 && Db::one("SELECT id FROM trainer_athletes WHERE athlete_id = ? AND status = 'ended'", [$evaId]) !== null);

echo "Cadastro de treinador e contas\n";
array_map('unlink', glob(BASE_PATH . '/storage/mail/*.txt') ?: []);
$adm->post('/admin/treinadores', ['name' => 'Novo Coach', 'email' => 'nao-e-email'], '/admin/treinadores');
check('e-mail inválido não cria', Db::one("SELECT id FROM users WHERE name = 'Novo Coach'") === null);
[$s] = $adm->post('/admin/treinadores', ['name' => 'Novo Coach', 'email' => 'trn@example.com'], '/admin/treinadores');
check('e-mail já usado = 422', $s === 422);
[$s] = $adm->post('/admin/treinadores', ['name' => 'Novo Coach', 'email' => 'coach@example.com', 'role' => 'admin'], '/admin/treinadores');
$coachId = $uid('coach@example.com');
$roles = Db::one('SELECT GROUP_CONCAT(r.slug) s FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?', [$coachId])['s'];
check('treinador criado só com perfil de treinador (ignora "role=admin")', $s === 303 && $roles === 'trainer', (string) $roles);
[$s, $b] = $adm->req('GET', "/admin/treinadores/$coachId");
preg_match('#/redefinir-senha/([0-9a-f]{64})#', $b, $m);
check('admin recebe o link para o treinador criar a senha; e-mail enviado', !empty($m[1]) && count(glob(BASE_PATH . '/storage/mail/*.txt') ?: []) === 1);
$guest = new Client();
[$s] = $guest->post("/redefinir-senha/{$m[1]}", ['password' => 'senha-do-coach-1', 'password2' => 'senha-do-coach-1'], "/redefinir-senha/{$m[1]}");
$coach = new Client();
[$s2] = $coach->post('/entrar', ['email' => 'coach@example.com', 'password' => 'senha-do-coach-1']);
check('o treinador define a senha pelo link e entra', $s === 303 && $s2 === 303 && $coach->header('Location') === '/treinador');

[$s] = $adm->post("/admin/usuarios/$coachId/status", ['action' => 'block'], "/admin/treinadores/$coachId");
[$s2] = $coach->req('GET', '/treinador');
$again = new Client();
[$s3] = $again->post('/entrar', ['email' => 'coach@example.com', 'password' => 'senha-do-coach-1']);
check('bloquear: desconecta na hora e impede novo login', $s === 303 && $s2 === 303 && $s3 === 401, "$s $s2 $s3");
$adm->post("/admin/usuarios/$coachId/status", ['action' => 'unblock'], "/admin/treinadores/$coachId");
Db::run('DELETE FROM rate_events');
$again2 = new Client();
[$s] = $again2->post('/entrar', ['email' => 'coach@example.com', 'password' => 'senha-do-coach-1']);
check('reativar: volta a entrar', $s === 303);
[$s] = $adm->post("/admin/usuarios/$admId/status", ['action' => 'block'], '/admin');
check('contas de administrador não podem ser bloqueadas pela tela (404)', $s === 404 && Db::one('SELECT status FROM users WHERE id = ?', [$admId])['status'] === 'active');
[$s] = $adm->post("/admin/usuarios/$admId/senha", [], '/admin');
check('nem recebem link de senha pela tela (404)', $s === 404);
[$s] = $tr1->post("/admin/usuarios/$coachId/status", ['action' => 'block'], '/treinador');
check('treinador não bloqueia contas (403)', $s === 403);
[$s] = $adm->req('POST', "/admin/usuarios/$coachId/status", ['action' => 'block']);
check('POST de admin sem CSRF = 419', $s === 419);

echo "Relatórios e auditoria\n";
[$s, $b] = $adm->req('GET', '/admin/relatorios');
check('relatório por treinador e por modalidade', $s === 200 && str_contains($b, 'Adesão') && str_contains($b, 'Triathlon'));
[$s, $b] = $adm->req('GET', '/admin/auditoria?acao=capacity_exception');
check('auditoria filtra por ação', $s === 200 && str_contains($b, 'capacity_exception') && !str_contains($b, '<td>login_success</td>'));
[$s, $b] = $adm->req('GET', '/admin/auditoria?dia=2000-01-01');
check('auditoria filtra por dia', str_contains($b, '0 registro(s)'));
[$s] = $adm->req('GET', '/admin/auditoria?pagina=999&dia=xx&acao=' . urlencode("' OR 1=1"));
check('filtros inválidos não quebram', $s === 200);
check('auditoria nunca guarda senhas ou links', Db::one("SELECT id FROM audit_log WHERE details LIKE '%senha-do-coach%' OR details LIKE '%redefinir-senha%'") === null);

echo "Sprint 6 — preços, contratos e pagamentos\n";
Db::run('DELETE FROM rate_events');
$adm = new Client(); $adm->post('/entrar', ['email' => 'adm@example.com', 'password' => 'senha-staff-12345']);
foreach (['/admin/precos', '/admin/contratos', '/admin/financeiro', "/admin/atletas/$mariaId/contrato"] as $path) {
    [$s] = $adm->req('GET', $path);
    [$s2] = $tr1->req('GET', $path);
    [$s3] = $mar->req('GET', $path);
    check(preg_replace('/\d+/', '{id}', $path) . ': admin 200; treinador e atleta 403', $s === 200 && $s2 === 403 && $s3 === 403, "$s $s2 $s3");
}
$B = Correon\Billing::class;
check('valores em centavos e leitura em formato brasileiro', $B::parseMoney('1.440,00') === 144000 && $B::parseMoney('180') === 18000 && $B::parseMoney('180,5') === 18050 && $B::parseMoney('abc') === null && $B::parseMoney('-5') === null && $B::parseMoney('0') === null);
check('parcelas somam o total (centavos na última)', $B::split(10000, 3) === [3333, 3333, 3334]);
check('soma de meses respeita o fim do mês', $B::addMonths('2026-01-31', 1) === '2026-02-28' && $B::endDate('2026-03-01', 'trimestral') === '2026-05-31');
$p = $B::priceTable();
check('tabela inicial: preços informados e combo só no mensal', $p['corrida']['mensal']['total'] === 18000 && $p['triathlon']['anual']['total'] === 144000 && $p['musculacao']['semestral']['max'] === 6
    && $p['corrida+musculacao']['mensal']['total'] === 20000 && $p['corrida+musculacao']['anual']['total'] === null);

echo "Contratos\n";
$eva = $uid('eva@example.com');
$today = date('Y-m-d');
$ct = static fn (array $o = []) => $o + ['product' => 'corrida', 'period' => 'mensal', 'condition' => 'standard', 'value' => '', 'reason' => '', 'installments' => '1', 'starts_on' => date('Y-m-d'), 'first_due' => ''];
$count = static fn () => (int) Db::one('SELECT COUNT(*) c FROM contracts')['c'];
$new = static function (int $athlete, array $data) use ($adm): array {
    [$s] = $adm->post("/admin/atletas/$athlete/contrato", $data, "/admin/atletas/$athlete/contrato");
    return [$s, (int) preg_replace('#.*/#', '', (string) $adm->header('Location'))];
};
foreach ([
    'sem produto' => ['product' => ''], 'combo sem preço no anual' => ['product' => 'corrida+musculacao', 'period' => 'anual'],
    'parcelas demais' => ['installments' => '2'], 'data inválida' => ['starts_on' => '2026-02-30'],
    'valor negociado sem justificativa' => ['condition' => 'custom', 'value' => '100'], 'valor negociado inválido' => ['condition' => 'custom', 'value' => 'abc', 'reason' => 'teste ok'],
    'aluno antigo em combo' => ['condition' => 'legacy_120', 'product' => 'corrida+musculacao'], 'aluno antigo no anual' => ['condition' => 'legacy_120', 'period' => 'anual'],
] as $label => $over) {
    [$s] = $new($eva, $ct($over));
    check("contrato recusado: $label (422)", $s === 422 && $count() === 0, "status=$s");
}
[$s] = $mar->post("/admin/atletas/$mariaId/contrato", $ct(['condition' => 'legacy_120']), '/app');
check('atleta NÃO se atribui a condição antiga (403)', $s === 403 && $count() === 0);
[$s, $cid] = $new($eva, $ct(['product' => 'corrida', 'period' => 'anual', 'installments' => '12', 'price_cents' => '1', 'value' => '1,00']));
$c = Db::one('SELECT * FROM contracts WHERE id = ?', [$cid]);
check('contrato anual de tabela: R$ 1.440 em 12x (ignora preço vindo do formulário)', $s === 303 && (int) $c['price_cents'] === 144000 && (int) Db::one('SELECT COUNT(*) c FROM charges WHERE contract_id = ?', [$cid])['c'] === 12);
check('12 parcelas de R$ 120 com vencimentos mensais', (int) Db::one('SELECT SUM(amount_cents) s FROM charges WHERE contract_id = ?', [$cid])['s'] === 144000
    && Db::one('SELECT due_on d FROM charges WHERE contract_id = ? AND seq = 2', [$cid])['d'] === $B::addMonths($today, 1));
[$s] = $new($eva, $ct(['product' => 'corrida']));
check('não duplica modalidade no mesmo período', $s === 422 && $count() === 1);
[$s] = $new($eva, $ct(['product' => 'triathlon']));
check('Corrida e Triathlon não se combinam', $s === 422 && $count() === 1);
[$s, $mus] = $new($eva, $ct(['product' => 'musculacao']));
check('modalidade independente com período próprio é aceita', $s === 303 && $count() === 2);

$joaoC = $uid('joao@example.com');
[$s, $leg] = $new($joaoC, $ct(['product' => 'corrida', 'condition' => 'legacy_120', 'starts_on' => date('Y-m-d', strtotime('-40 days'))]));
check('aluno antigo: R$ 120 gravado no contrato', $s === 303 && (int) Db::one('SELECT price_cents p FROM contracts WHERE id = ?', [$leg])['p'] === 12000);
check('quem aplicou a condição antiga fica registrado', Db::one("SELECT id FROM audit_log WHERE action = 'contract_created' AND entity_id = ? AND details LIKE '%legacy_120%'", [$leg]) !== null);
[$s, $neg] = $new($mariaId, $ct(['product' => 'musculacao', 'period' => 'trimestral', 'condition' => 'custom', 'value' => '400,00', 'reason' => 'Acordo <b>antigo</b>', 'installments' => '3']));
check('valor negociado com justificativa (3 parcelas)', $s === 303 && (int) Db::one('SELECT SUM(amount_cents) s FROM charges WHERE contract_id = ?', [$neg])['s'] === 40000);
[, $b] = $adm->req('GET', "/admin/contratos/$neg");
check('justificativa escapada', str_contains($b, 'Acordo &lt;b&gt;') && !str_contains($b, '<b>antigo</b>'));

echo "Mudança de preço não altera contratos\n";
$post = ['price' => [], 'max' => []];
foreach ($B::priceTable() as $prod => $periods) { foreach ($periods as $per => $row) { $post['price'][$prod][$per] = $row['total'] === null ? '' : number_format($row['total'] / 100, 2, ',', '.'); $post['max'][$prod][$per] = (string) $row['max']; } }
$bad = $post; $bad['price']['corrida']['mensal'] = 'xx';
$adm->post('/admin/precos', $bad, '/admin/precos');
check('preço inválido: nada é salvo', $B::priceTable()['corrida']['mensal']['total'] === 18000);
$post['price']['corrida']['anual'] = '1.500,00';
$adm->post('/admin/precos', $post, '/admin/precos');
check('admin altera o preço de tabela', $B::priceTable()['corrida']['anual']['total'] === 150000);
check('contrato existente mantém o valor aplicado', (int) Db::one('SELECT price_cents p FROM contracts WHERE id = ?', [$cid])['p'] === 144000);
check('mudança de preço auditada com antes e depois', Db::one("SELECT id FROM audit_log WHERE action = 'price_changed' AND details LIKE '%144000%' AND details LIKE '%150000%'") !== null);

echo "Pagamentos\n";
$ch1 = (int) Db::one('SELECT id FROM charges WHERE contract_id = ? AND seq = 1', [$cid])['id'];
$page = "/admin/contratos/$cid";
$status = static fn (int $id) => Db::one('SELECT status s FROM charges WHERE id = ?', [$id])['s'];
$adm->post("/admin/cobrancas/$ch1/pagar", ['paid_on' => date('Y-m-d', strtotime('+1 day')), 'method' => 'pix'], $page);
check('data futura não é aceita', $status($ch1) === 'open');
$adm->post("/admin/cobrancas/$ch1/pagar", ['paid_on' => $today, 'method' => 'cheque'], $page);
check('forma de pagamento inválida não é aceita', $status($ch1) === 'open');
[$s] = $mar->post("/admin/cobrancas/$ch1/pagar", ['paid_on' => $today, 'method' => 'pix'], '/app');
check('atleta não dá baixa (403)', $s === 403 && $status($ch1) === 'open');
$adm->post("/admin/cobrancas/$ch1/pagar", ['paid_on' => $today, 'method' => 'pix'], $page);
check('baixa manual registrada', $status($ch1) === 'paid');
$events = static fn () => (int) Db::one("SELECT COUNT(*) c FROM contract_events WHERE charge_id = ? AND event = 'paid'", [$ch1])['c'];
$adm->post("/admin/cobrancas/$ch1/pagar", ['paid_on' => $today, 'method' => 'pix'], $page);
check('repetir a baixa não duplica (idempotente)', $events() === 1);
$adm->post("/admin/cobrancas/$ch1/estornar", ['reason' => ''], $page);
check('estorno exige motivo', $status($ch1) === 'paid');
$adm->post("/admin/cobrancas/$ch1/estornar", ['reason' => 'lançado errado'], $page);
check('estorno reabre a parcela e fica no histórico', $status($ch1) === 'open' && Db::one("SELECT id FROM contract_events WHERE charge_id = ? AND event = 'reversed'", [$ch1]) !== null);
$adm->post("/admin/cobrancas/$ch1/pagar", ['paid_on' => $today, 'method' => 'pix'], $page);

echo "Atraso, renovação, troca e cancelamento\n";
[$s, $b] = $adm->req('GET', '/admin/financeiro');
check('financeiro mostra recebido e atraso reais', str_contains($b, 'R$ 120,00') && (int) Db::one("SELECT COUNT(*) c FROM charges WHERE status = 'open' AND due_on < CURDATE()")['c'] >= 1);
[$s, $b] = $adm->req('GET', '/admin/contratos?filtro=atrasados');
check('filtro de atrasados mostra o contrato vencido', str_contains($b, "/admin/contratos/$leg") && !str_contains($b, "/admin/contratos/$neg\""));
$adm->post("/admin/contratos/$leg/renovar", [], "/admin/contratos/$leg");
$ren = Db::one('SELECT * FROM contracts WHERE renewed_from = ?', [$leg]);
check('renovar mantém R$ 120 e a condição antiga', $ren && (int) $ren['price_cents'] === 12000 && $ren['price_condition'] === 'legacy_120' && $ren['starts_on'] === date('Y-m-d', strtotime(Db::one('SELECT ends_on e FROM contracts WHERE id = ?', [$leg])['e'] . ' +1 day')));
$before = $count();
$adm->post("/admin/contratos/$leg/renovar", [], "/admin/contratos/$leg");
check('não renova duas vezes', $count() === $before);
$adm->post("/admin/contratos/$cid/trocar", [], $page);
$sw = Db::one('SELECT product, price_cents FROM contracts WHERE id = ?', [$cid]);
check('troca Corrida → Triathlon mantém o valor', $sw['product'] === 'triathlon' && (int) $sw['price_cents'] === 144000);
$adm->post("/admin/contratos/$mus/trocar", [], "/admin/contratos/$mus");
check('musculação não tem troca', Db::one('SELECT product p FROM contracts WHERE id = ?', [$mus])['p'] === 'musculacao');
$adm->post("$page/cancelar", ['reason' => ''], $page);
check('cancelamento exige motivo', Db::one('SELECT status s FROM contracts WHERE id = ?', [$cid])['s'] === 'active');
$adm->post("$page/cancelar", ['reason' => 'Mudança de cidade'], $page);
check('cancelar: parcelas abertas canceladas, a paga permanece', Db::one('SELECT status s FROM contracts WHERE id = ?', [$cid])['s'] === 'cancelled' && $status($ch1) === 'paid'
    && (int) Db::one("SELECT COUNT(*) c FROM charges WHERE contract_id = ? AND status = 'cancelled'", [$cid])['c'] === 11);
check('nada é apagado', (int) Db::one('SELECT COUNT(*) c FROM charges WHERE contract_id = ?', [$cid])['c'] === 12);

echo "Visão do atleta\n";
$jo = new Client(); Db::run('DELETE FROM rate_events'); $jo->post('/entrar', ['email' => 'joao@example.com', 'password' => 'senha-forte-123']);
[$s, $b] = $jo->req('GET', '/app/assinaturas');
check('atleta vê o próprio contrato e parcelas', $s === 200 && str_contains($b, 'R$ 120,00') && str_contains($b, 'Em atraso'));
check('atleta não vê dados de outros atletas', !str_contains($b, 'R$ 400,00') && !str_contains($b, 'R$ 1.440,00'));
check('rótulo interno "aluno antigo" não é exposto ao atleta', !str_contains($b, 'Aluno antigo'));
[$s, $b] = $jo->req('GET', '/app/modalidades');
check('modalidades vêm do contrato vigente', $s === 200 && str_contains($b, 'Corrida'));
[$s, $b] = $jo->req('GET', '/app');
check('painel do atleta mostra assinatura e atraso reais', str_contains($b, 'parcela(s) em atraso'));
[$s] = $jo->req('GET', "/admin/contratos/$neg");
check('atleta não abre contrato pelo endereço do admin (403)', $s === 403);

echo "\nResultado: $pass ok, $fail falha(s)\n";
exit($fail ? 1 : 0);
