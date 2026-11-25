<?php /** @var string $inner @var string $active */
$items = [
	'home' => ['/app', 'Início'],
	'perfil' => ['/app/perfil', 'Meu perfil'],
	'modalidades' => ['/app/modalidades', 'Minhas modalidades'],
	'treinos' => ['/app/treinos', 'Meus treinos'],
	'treinador' => ['/app/treinador', 'Meu treinador'],
	'personal' => ['/app/personal', 'Personal Trainer'],
	'assinaturas' => ['/app/assinaturas', 'Assinaturas'],
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
