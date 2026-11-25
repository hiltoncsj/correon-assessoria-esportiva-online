<?php
declare(strict_types=1);
// Uso: php backend/bin/set-capacity.php <email_treinador> <limite_online> [limite_presencial]
// Ajusta a capacidade do treinador (padrão online: 30). A tela de administração virá na Sprint 5.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/src/bootstrap.php';

use Correon\{Audit, Capacity, Db};

[$self, $email, $online, $presencial] = $argv + [null, null, null, null];
if (!$email || !ctype_digit((string) $online) || (int) $online < 1 || (int) $online > 1000 || ($presencial !== null && !ctype_digit($presencial))) {
    fwrite(STDERR, "Uso: php set-capacity.php <email_treinador> <limite_online 1-1000> [limite_presencial]\n");
    exit(1);
}
$t = Db::one("SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE u.email = ? AND r.slug = 'trainer'", [strtolower($email)]);
if (!$t) {
    fwrite(STDERR, "Treinador não encontrado.\n");
    exit(1);
}
Capacity::setLimits((int) $t['id'], (int) $online, $presencial === null ? null : (int) $presencial);
Audit::log('capacity_changed', null, 'user', (int) $t['id'], ['online' => (int) $online, 'presencial' => $presencial]);
$c = Capacity::status((int) $t['id']);
echo "Limite online: {$c['limit']} (em uso: {$c['active']}).\n";
