<?php /** @var string $label @var array{next:array,past:array} $goals @var string $base @var string $action @var array<string,string> $old @var ?string $error @var string $note */ ?>
<?php if (!empty($back)): ?><p class="eyebrow"><a href="<?= e($back) ?>">← <?= e($label) ?></a></p><?php else: ?><p class="eyebrow"><?= e($label) ?></p><?php endif; ?>
<h1>Objetivos e provas</h1>
<p class="muted"><?= e($note) ?></p>

<details class="tile newbox"<?= $error ? ' open' : '' ?>>
	<summary>+ Novo objetivo ou prova</summary>
	<form class="form" method="post" action="<?= e($action) ?>" novalidate>
		<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
		<?php if ($error): ?><p class="notice notice--err" role="alert"><?= e($error) ?></p><?php endif; ?>
		<?php require __DIR__ . '/fields.php'; ?>
		<div class="actions"><button class="btn btn--sm" type="submit">Cadastrar</button></div>
	</form>
</details>

<section class="tile" aria-labelledby="h-next">
	<h2 id="h-next">Próximos</h2>
	<?php if (!$goals['next']): ?><p class="muted">Nada planejado.</p><?php else: $items = $goals['next']; require __DIR__ . '/items.php'; endif; ?>
</section>
<?php if ($goals['past']): ?>
	<section class="tile" aria-labelledby="h-past">
		<h2 id="h-past">Histórico</h2>
		<?php $items = $goals['past']; require __DIR__ . '/items.php'; ?>
	</section>
<?php endif; ?>
