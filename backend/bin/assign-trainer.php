<?php
declare(strict_types=1);
// Uso: php backend/bin/assign-trainer.php <assign|end> <email_treinador> <email_atleta> <corrida|triathlon|musculacao>
// Vincula (ou encerra o vínculo de) um treinador a um atleta numa modalidade. A tela de administração virá na Sprint 5.
// Com o treinador no limite de atletas, a atribuição é bloqueada; para abrir exceção: --excecao="motivo" ao final.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/src/bootstrap.php';

use Correon\{Audit, Capacity, Db};

$reason = '';
foreach ($argv as $i => $arg) {
    if (str_starts_with($arg, '--excecao=')) {
        $reason = trim(substr($arg, 10));
        unset($argv[$i]);
    }
}
[$self, $op, $tEmail, $aEmail, $mod] = array_values($argv) + [null, null, null, null, null];
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
    $tid = (int) $t['id'];
    $aid = (int) $a['id'];
    $exception = !Capacity::canAssign($tid, $aid);
    if ($exception && $reason === '') {
        $c = Capacity::status($tid);
        fwrite(STDERR, "BLOQUEADO: treinador no limite ({$c['active']} de {$c['limit']} atletas). Para abrir exceção, acrescente --excecao=\"motivo\".\n");
        Audit::log('capacity_blocked', null, 'user', $aid, ['trainer_id' => $tid]);
        exit(2);
    }
    if ($exception) {
        Db::run('INSERT INTO capacity_exceptions (trainer_id, athlete_id, reason) VALUES (?,?,?)', [$tid, $aid, mb_substr($reason, 0, 300)]);
        Audit::log('capacity_exception', null, 'user', $aid, ['trainer_id' => $tid]);
        echo "Exceção de capacidade registrada.\n";
    }
    Db::run("INSERT INTO trainer_athletes (trainer_id, athlete_id, modality_id) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE status = 'active', ended_at = NULL, started_at = IF(status = 'ended', NOW(), started_at)",
        [$t['id'], $a['id'], $m['id']]);
    Audit::log('trainer_assigned', null, 'user', (int) $a['id'], ['trainer_id' => (int) $t['id'], 'modality' => $mod]);
    echo "Vínculo ativo: treinador #{$t['id']} ↔ atleta #{$a['id']} ($mod).\n";
    $c = Capacity::status($tid);
    if ($c['state'] !== 'ok') {
        echo "ATENÇÃO: treinador com {$c['active']} de {$c['limit']} atletas ({$c['pct']}%).\n";
    }
} else {
    Db::run("UPDATE trainer_athletes SET status = 'ended', ended_at = NOW() WHERE trainer_id = ? AND athlete_id = ? AND modality_id = ? AND status = 'active'",
        [$t['id'], $a['id'], $m['id']]);
    Audit::log('trainer_unassigned', null, 'user', (int) $a['id'], ['trainer_id' => (int) $t['id'], 'modality' => $mod]);
    echo "Vínculo encerrado (histórico preservado).\n";
}
