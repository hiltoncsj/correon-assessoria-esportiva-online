<?php /** @var string $inner @var string $active */
$items = [
	'inicio' => ['/admin', 'Início'],
	'atletas' => ['/admin/atletas', 'Atletas'],
	'treinadores' => ['/admin/treinadores', 'Treinadores'],
	'personal' => ['/admin/personal', 'Personal'],
	'contratos' => ['/admin/contratos', 'Contratos'],
	'precos' => ['/admin/precos', 'Preços'],
	'financeiro' => ['/admin/financeiro', 'Financeiro'],
	'relatorios' => ['/admin/relatorios', 'Relatórios'],
	'auditoria' => ['/admin/auditoria', 'Auditoria'],
];
?>
<div class="shell">
	<nav class="side" aria-label="Painel administrativo">
		<ul>
			<?php foreach ($items as $key => [$href, $label]): ?>
				<li><a href="<?= e($href) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
			<?php endforeach; ?>
		</ul>
	</nav>
	<div class="shell__main"><?= $inner ?></div>
</div>
