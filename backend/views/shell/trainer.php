<?php /** @var string $inner @var string $active */
$unseen = \Correon\TrainerPanel::unseen((int) $user['id']);
$ptPending = (int) \Correon\Db::one("SELECT COUNT(*) c FROM pt_sessions WHERE trainer_id = ? AND status = 'requested'", [(int) $user['id']])['c'];
$items = [
	'inicio' => ['/treinador', 'Início'],
	'home' => ['/treinador/atletas', 'Meus atletas'],
	'modelos' => ['/treinador/modelos', 'Modelos de treino'],
	'aplicar' => ['/treinador/modelos/aplicar', 'Aplicar modelos'],
	'zonas' => ['/treinador/zonas', 'Zonas de FC'],
	'objetivos' => ['/treinador/objetivos', 'Objetivos e provas'],
	'acompanhamento' => ['/treinador/acompanhamento', 'Acompanhamento'],
	'agenda' => ['/treinador/agenda', 'Agenda'],
	'personal' => ['/treinador/personal', 'Personal' . ($ptPending ? " ($ptPending)" : '')],
	'disponibilidade' => ['/treinador/disponibilidade', 'Disponibilidade'],
	'novidades' => ['/treinador/novidades', 'Novidades' . ($unseen ? " ($unseen)" : '')],
	'senha' => ['/treinador/senha', 'Alterar senha'],
];
?>
<div class="shell">
	<nav class="side" aria-label="Painel do treinador">
		<ul>
			<?php foreach ($items as $key => [$href, $label]): ?>
				<li><a href="<?= e($href) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
			<?php endforeach; ?>
		</ul>
	</nav>
	<div class="shell__main"><?= $inner ?></div>
</div>
