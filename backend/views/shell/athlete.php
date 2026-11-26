<?php /** @var string $inner @var string $active */
$unreadN = \Correon\Notify::unread((int) $user['id']);
$items = [
	'home' => ['/app', 'Início'],
	'avisos' => ['/app/avisos', 'Avisos' . ($unreadN ? " ($unreadN)" : '')],
	'perfil' => ['/app/perfil', 'Meu perfil'],
	'modalidades' => ['/app/modalidades', 'Minhas modalidades'],
	'treinos' => ['/app/treinos', 'Meus treinos'],
	'treinador' => ['/app/treinador', 'Meu treinador'],
	'objetivos' => ['/app/objetivos', 'Objetivos e provas'],
	'personal' => ['/app/personal', 'Personal Trainer'],
	'assinaturas' => ['/app/assinaturas', 'Assinaturas'],
	'privacidade' => ['/app/privacidade', 'Privacidade'],
	'senha' => ['/app/senha', 'Alterar senha'],
];
?>
<div class="shell">
	<nav class="side" aria-label="Área do atleta">
		<ul>
			<?php foreach ($items as $key => [$href, $label]): ?>
				<li><a href="<?= e($href) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
			<?php endforeach; ?>
		</ul>
	</nav>
	<div class="shell__main"><?= $inner ?></div>
</div>
