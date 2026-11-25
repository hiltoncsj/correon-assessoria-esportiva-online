<?php
declare(strict_types=1);
// Uso: php backend/bin/assign-trainer.php <assign|end> <email_treinador> <email_atleta> <corrida|triathlon|musculacao>
// Mesma regra da tela do administrador. Com o treinador no limite de atletas, a atribuição é bloqueada;
// para abrir exceção: --excecao="motivo" ao final.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/src/bootstrap.php';

use Correon\{Assignment, Capacity, Db};

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
$id = static fn (string $email): int => (int) (Db::one('SELECT id FROM users WHERE email = ?', [strtolower($email)])['id'] ?? 0);
$t = $id($tEmail);
$a = $id($aEmail);

if ($op === 'end') {
    echo Assignment::end($t, $a, $mod) ? "Vínculo encerrado (histórico preservado).\n" : "Não havia vínculo ativo.\n";
    exit(0);
}
$r = Assignment::assign($t, $a, $mod, $reason);
if ($r['result'] === Assignment::INVALID) {
    fwrite(STDERR, "Treinador, atleta ou modalidade não encontrados (o e-mail precisa ter o perfil correto).\n");
    exit(1);
}
$c = Capacity::status($t);
if ($r['result'] === Assignment::BLOCKED) {
    fwrite(STDERR, "BLOQUEADO: treinador no limite ({$c['active']} de {$c['limit']} atletas). Para abrir exceção, acrescente --excecao=\"motivo\".\n");
    exit(2);
}
echo ($r['exception'] ? "Exceção de capacidade registrada.\n" : '') . "Vínculo ativo: treinador #$t ↔ atleta #$a ($mod).\n";
if ($c['state'] !== 'ok') {
    echo "ATENÇÃO: treinador com {$c['active']} de {$c['limit']} atletas ({$c['pct']}%).\n";
}
