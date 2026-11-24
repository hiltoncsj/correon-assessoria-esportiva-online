<?php /** @var string $heading @var string $role @var array $u @var string $note @var ?array $counts */ ?>
<section class="card card--wide">
	<p class="eyebrow"><?= e($role) ?></p>
	<h1><?= e($heading) ?></h1>
	<p>Olá, <?= e($u['name']) ?>.</p>
	<dl class="facts">
		<dt>E-mail</dt><dd><?= e($u['email']) ?></dd>
		<dt>Perfil</dt><dd><?= e($role) ?></dd>
	</dl>
	<?php if (!empty($counts)): ?>
		<h2>Usuários por perfil</h2>
		<ul class="facts">
			<?php foreach ($counts as $c): ?><li><?= e($c['name']) ?>: <strong><?= (int) $c['total'] ?></strong></li><?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<p class="muted"><?= e($note) ?></p>
</section>
