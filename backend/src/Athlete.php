<?php
declare(strict_types=1);
namespace Correon;

/** Área do atleta (Sprint 2). Todo acesso é ao PRÓPRIO usuário logado: nenhuma rota recebe id de usuário. */
final class Athlete
{
    private const STATES = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

    /** Seções futuras: título, descrição do que vai aparecer e sprint prevista. */
    public const SOON = [
        'modalidades' => ['Minhas modalidades', 'Aqui aparecerão as modalidades contratadas (Corrida, Triathlon, Musculação) e a periodicidade de cada uma.'],
        'treinos' => ['Meus treinos', 'Aqui aparecerão os treinos publicados pelo seu treinador, o calendário e o registro dos treinos realizados.'],
        'treinador' => ['Meu treinador', 'Aqui aparecerão os dados do treinador responsável por você em cada modalidade.'],
        'assinaturas' => ['Assinaturas e pagamentos', 'Aqui aparecerão seus contratos, a situação de cada assinatura e o histórico de pagamentos.'],
    ];

    public static function dashboard(): never
    {
        $u = App::require('athlete');
        $profile = self::profile((int) $u['id']);
        $meta = Db::one('SELECT created_at, last_login_at FROM users WHERE id = ?', [$u['id']]) ?? [];
        // Modalidades, treinos, treinador e assinatura ainda não têm tabelas (Sprints 3, 4 e 6):
        // por isso a tela mostra o estado vazio real, sem números.
        View::render('athlete/dashboard', [
            'title' => 'Minha área', 'shell' => 'athlete', 'active' => 'home',
            'u' => $u, 'meta' => $meta, 'completeness' => self::completeness($profile),
        ]);
    }

    public static function soon(string $section): never
    {
        App::require('athlete');
        [$title, $text] = self::SOON[$section];
        View::render('athlete/soon', ['title' => $title, 'shell' => 'athlete', 'active' => $section, 'text' => $text]);
    }

    public static function profileForm(): never
    {
        $u = App::require('athlete');
        View::render('athlete/profile', [
            'title' => 'Meu perfil', 'shell' => 'athlete', 'active' => 'perfil', 'u' => $u,
            'old' => self::profile((int) $u['id']), 'errors' => [], 'states' => self::STATES,
        ]);
    }

    public static function profileSave(): never
    {
        $u = App::require('athlete');
        $in = [];
        foreach (['name', 'phone', 'birth_date', 'city', 'state', 'goal', 'sports_background', 'emergency_name', 'emergency_phone'] as $k) {
            $in[$k] = preg_replace('/[ \t]+/u', ' ', Http::input($k)) ?? '';
        }
        $errors = [];

        if (mb_strlen($in['name']) < 2 || mb_strlen($in['name']) > 120) {
            $errors['name'] = 'Informe seu nome (2 a 120 caracteres).';
        }
        $phone = self::digits($in['phone']);
        if ($in['phone'] !== '' && !self::validPhone($phone)) {
            $errors['phone'] = 'Telefone inválido. Use DDD + número.';
        }
        $ephone = self::digits($in['emergency_phone']);
        if ($in['emergency_phone'] !== '' && !self::validPhone($ephone)) {
            $errors['emergency_phone'] = 'Telefone inválido. Use DDD + número.';
        }
        if ($in['birth_date'] !== '') {
            $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $in['birth_date']);
            $ok = $d && $d->format('Y-m-d') === $in['birth_date'];
            if (!$ok || $d > new \DateTimeImmutable('today') || $d < new \DateTimeImmutable('1900-01-01')) {
                $errors['birth_date'] = 'Data de nascimento inválida.';
            } elseif ($d > new \DateTimeImmutable('-13 years')) {
                $errors['birth_date'] = 'O cadastro é permitido a partir de 13 anos.';
            }
        }
        if (mb_strlen($in['city']) > 80) {
            $errors['city'] = 'Cidade muito longa (máx. 80).';
        }
        if ($in['state'] !== '' && !in_array($in['state'], self::STATES, true)) {
            $errors['state'] = 'Estado inválido.';
        }
        if (mb_strlen($in['goal']) > 500) {
            $errors['goal'] = 'Máximo de 500 caracteres.';
        }
        if (mb_strlen($in['sports_background']) > 1000) {
            $errors['sports_background'] = 'Máximo de 1000 caracteres.';
        }
        if (mb_strlen($in['emergency_name']) > 120) {
            $errors['emergency_name'] = 'Máximo de 120 caracteres.';
        }
        if (($in['emergency_name'] === '') !== ($ephone === '')) {
            $errors['emergency_name'] = 'Informe nome e telefone do contato de emergência, ou deixe os dois em branco.';
        }

        if ($errors) {
            View::render('athlete/profile', [
                'title' => 'Meu perfil', 'shell' => 'athlete', 'active' => 'perfil', 'u' => $u,
                'old' => $in, 'errors' => $errors, 'states' => self::STATES,
            ], 422);
        }

        $n = static fn (string $v): ?string => $v === '' ? null : $v;
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            Db::run('UPDATE users SET name = ? WHERE id = ?', [$in['name'], $u['id']]);
            Db::run(
                'INSERT INTO athlete_profiles (user_id, phone, birth_date, city, state, goal, sports_background, emergency_name, emergency_phone)
                 VALUES (?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE phone=VALUES(phone), birth_date=VALUES(birth_date), city=VALUES(city), state=VALUES(state),
                   goal=VALUES(goal), sports_background=VALUES(sports_background), emergency_name=VALUES(emergency_name), emergency_phone=VALUES(emergency_phone)',
                [$u['id'], $n($phone), $n($in['birth_date']), $n($in['city']), $n($in['state']), $n($in['goal']),
                    $n($in['sports_background']), $n($in['emergency_name']), $n($ephone)]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        // Auditoria registra só QUAIS campos existem, nunca os valores pessoais.
        Audit::log('profile_updated', (int) $u['id'], 'athlete_profile', (int) $u['id'], ['fields' => array_keys(array_filter($in, static fn ($v) => $v !== ''))]);
        Http::flash('ok', 'Perfil atualizado.');
        Http::redirect('/app/perfil');
    }

    public static function passwordForm(): never
    {
        $u = App::require('athlete');
        View::render('athlete/password', ['title' => 'Alterar senha', 'shell' => 'athlete', 'active' => 'senha', 'u' => $u, 'error' => null]);
    }

    public static function passwordSave(): never
    {
        $u = App::require('athlete');
        $id = (int) $u['id'];
        $render = static fn (string $err, int $status) => View::render('athlete/password', [
            'title' => 'Alterar senha', 'shell' => 'athlete', 'active' => 'senha', 'u' => $u, 'error' => $err,
        ], $status);

        if (RateLimiter::tooMany('pwchange', (string) $id, 5, 900)) {
            $render('Muitas tentativas. Aguarde alguns minutos.', 429);
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
            $render($err, 422);
        }
        RateLimiter::hit('pwchange', (string) $id);
        Db::run('UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $id]);
        Auth::login($id); // nova sessão com a versão atual; as outras sessões deixam de valer
        Audit::log('password_changed', $id, 'user', $id);
        Mailer::send((string) $u['email'], 'CorreON — sua senha foi alterada',
            "Olá, {$u['name']}.

A senha da sua conta CorreON foi alterada em " . date('d/m/Y H:i') . ".
"
            . "Se não foi você, recupere o acesso em " . rtrim((string) Env::get('APP_URL', ''), '/') . "/recuperar-senha e avise a CorreON.");
        Http::flash('ok', 'Senha alterada. Os outros dispositivos foram desconectados.');
        Http::redirect('/app/senha');
    }

    // ---- auxiliares ----

    /** @return array<string,string> */
    public static function profile(int $userId): array
    {
        $row = Db::one(
            'SELECT u.name, p.phone, p.birth_date, p.city, p.state, p.goal, p.sports_background, p.emergency_name, p.emergency_phone
             FROM users u LEFT JOIN athlete_profiles p ON p.user_id = u.id WHERE u.id = ?',
            [$userId]
        ) ?? [];
        return array_map(static fn ($v) => (string) ($v ?? ''), $row);
    }

    /** Percentual real de campos-chave preenchidos. @param array<string,string> $p */
    public static function completeness(array $p): int
    {
        $keys = ['phone', 'birth_date', 'city', 'state', 'goal', 'sports_background', 'emergency_name', 'emergency_phone'];
        $filled = count(array_filter($keys, static fn ($k) => ($p[$k] ?? '') !== ''));
        return (int) round($filled / count($keys) * 100);
    }

    private static function digits(string $s): string
    {
        return preg_replace('/\D+/', '', $s) ?? '';
    }

    private static function validPhone(string $d): bool
    {
        return preg_match('/^[1-9]{2}9?\d{8}$/', $d) === 1;
    }
}
