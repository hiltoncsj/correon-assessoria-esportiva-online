<?php /** @var string $back @var string $action @var array<string,string> $old @var ?string $error */ ?>
<p class="eyebrow"><a href="<?= e($back) ?>">← Objetivos e provas</a></p>
<h1>Editar</h1>
<form class="tile form" method="post" action="<?= e($action) ?>" novalidate>
	<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
	<?php if ($error): ?><p class="notice notice--err" role="alert"><?= e($error) ?></p><?php endif; ?>
	<?php require __DIR__ . '/fields.php'; ?>
	<p class="muted">Se não for mais acontecer, mude a situação para Cancelado.</p>
	<div class="actions"><button class="btn btn--sm" type="submit">Salvar</button></div>
</form>
