<?php
declare(strict_types=1);
// Uso: php backend/bin/create-user.php <admin|trainer|athlete> "Nome" email@dominio.com
// A senha é pedida no terminal (não fica no histórico). Admin e treinador só são criados aqui.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/src/bootstrap.php';

use Correon\{Audit, Auth, Db};

[$self, $role, $name, $email] = $argv + [null, null, null, null];
if (!in_array($role, ['admin', 'trainer', 'athlete'], true) || !$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php create-user.php <admin|trainer|athlete> \"Nome\" email\n");
    exit(1);
}
$email = strtolower($email);
$pass = getenv('CORREON_NEW_PASSWORD') ?: '';
if ($pass === '') {
    fwrite(STDOUT, 'Senha (min. ' . Auth::PASSWORD_MIN . ' caracteres): ');
    $pass = trim((string) fgets(STDIN));
}
if (($err = Auth::passwordError($pass)) !== null) {
    fwrite(STDERR, "$err\n");
    exit(1);
}
try {
    $id = Auth::createUser($name, $email, $pass, $role, false);
    Audit::log('user_created_cli', null, 'user', $id, ['role' => $role]);
    echo "Usuário #$id criado com perfil $role.\n";
} catch (PDOException $e) {
    fwrite(STDERR, $e->getCode() === '23000' ? "E-mail já cadastrado.\n" : 'Erro: ' . $e->getMessage() . "\n");
    exit(1);
}
