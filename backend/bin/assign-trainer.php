<?php
declare(strict_types=1);
// Uso: php backend/bin/assign-trainer.php <assign|end> <email_treinador> <email_atleta> <corrida|triathlon|musculacao>
// Vincula (ou encerra o vínculo de) um treinador a um atleta numa modalidade. A tela de administração virá na Sprint 5.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/src/bootstrap.php';

use Correon\{Audit, Db};

[$self, $op, $tEmail, $aEmail, $mod] = $argv + [null, null, null, null, null];
if (!in_array($op, ['assign', 'end'], true) || !$tEmail || !$aEmail || !$mod) {
    fwrite(STDERR, "Uso: php assign-trainer.php <assign|end> <email_treinador> <email_atleta> <modalidade>\n");
    exit(1);
}
$find = static fn (string $email, string $role) => Db::one(
    'SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE u.email = ? AND r.slug = ?',
    [strtolower($email), $role]
);
$t = $find($tEmail, 'trainer');
$a = $find($aEmail, 'athlete');
$m = Db::one('SELECT id FROM modalities WHERE slug = ?', [$mod]);
if (!$t || !$a || !$m) {
    fwrite(STDERR, "Treinador, atleta ou modalidade não encontrados (o e-mail precisa ter o perfil correto).\n");
    exit(1);
}
if ($op === 'assign') {
    Db::run("INSERT INTO trainer_athletes (trainer_id, athlete_id, modality_id) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE status = 'active', ended_at = NULL, started_at = IF(status = 'ended', NOW(), started_at)",
        [$t['id'], $a['id'], $m['id']]);
    Audit::log('trainer_assigned', null, 'user', (int) $a['id'], ['trainer_id' => (int) $t['id'], 'modality' => $mod]);
    echo "Vínculo ativo: treinador #{$t['id']} ↔ atleta #{$a['id']} ($mod).\n";
} else {
    Db::run("UPDATE trainer_athletes SET status = 'ended', ended_at = NOW() WHERE trainer_id = ? AND athlete_id = ? AND modality_id = ? AND status = 'active'",
        [$t['id'], $a['id'], $m['id']]);
    Audit::log('trainer_unassigned', null, 'user', (int) $a['id'], ['trainer_id' => (int) $t['id'], 'modality' => $mod]);
    echo "Vínculo encerrado (histórico preservado).\n";
}
