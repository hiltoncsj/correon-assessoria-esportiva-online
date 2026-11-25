<?php
declare(strict_types=1);
// Uso (NO SEU COMPUTADOR): php admin-sql.php "Nome" email@dominio.com
// Gera o SQL do primeiro administrador para colar no phpMyAdmin (hospedagem sem terminal).
// A senha é digitada aqui e só o hash vai para o SQL.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
[$self, $name, $email] = $argv + [null, null, null];
if (!$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php admin-sql.php \"Nome\" email@dominio.com\n");
    exit(1);
}
$pass = getenv('CORREON_NEW_PASSWORD') ?: '';
if ($pass === '') {
    fwrite(STDERR, 'Senha do administrador (min. 12 caracteres): ');
    $pass = trim((string) fgets(STDIN));
}
if (mb_strlen($pass) < 12 || strlen($pass) > 72) {
    fwrite(STDERR, "A senha do administrador deve ter de 12 a 72 caracteres.\n");
    exit(1);
}
$q = static fn (string $s): string => "'" . str_replace(['\\', "'"], ['\\\\', "''"], $s) . "'";
echo "-- Cole no phpMyAdmin (aba SQL) do banco de produção. Apague este texto depois.\n";
echo 'INSERT INTO users (name, email, password_hash) VALUES (' . $q($name) . ', ' . $q(strtolower($email)) . ', ' . $q(password_hash($pass, PASSWORD_DEFAULT)) . ");\n";
echo "INSERT INTO user_roles (user_id, role_id) SELECT LAST_INSERT_ID(), id FROM roles WHERE slug = 'admin';\n";
