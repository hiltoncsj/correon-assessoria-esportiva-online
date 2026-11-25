<?php /** @var string $inner @var string $active */
$items = [
	'home' => ['/treinador', 'Meus atletas'],
	'modelos' => ['/treinador/modelos', 'Modelos de treino'],
	'aplicar' => ['/treinador/modelos/aplicar', 'Aplicar modelos'],
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
