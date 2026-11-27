<?php /** @var int $goalId @var array<string,string> $old @var ?string $error @var list<array<string,mixed>> $participants @var int $others */ ?>
<p class="eyebrow"><a href="/treinador/objetivos">← Objetivos e provas</a></p>
<h1><?= e($old['name']) ?></h1>

<form class="tile form" method="post" action="/treinador/objetivos/<?= (int) $goalId ?>" novalidate>
	<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
	<?php if ($error): ?><p class="notice notice--err" role="alert"><?= e($error) ?></p><?php endif; ?>
	<?php require __DIR__ . '/fields.php'; ?>
	<div class="actions"><button class="btn btn--sm" type="submit">Salvar</button></div>
</form>

<section class="tile" aria-labelledby="h-part">
	<h2 id="h-part">Meus atletas inscritos</h2>
	<?php if (!$participants): ?><p class="muted">Nenhum dos seus atletas colocou o nome.</p><?php else: ?>
		<ul class="done-list">
			<?php foreach ($participants as $p): ?>
				<li>
					<a href="/treinador/atletas/<?= (int) $p['id'] ?>"><?= e($p['name']) ?></a>
					<span class="muted"><?= e($p['result'] ? 'Resultado: ' . $p['result'] : '') ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<?php if ($others > 0): ?><p class="muted">Mais <?= (int) $others ?> inscrito(s) de outros treinadores.</p><?php endif; ?>
</section>

<form method="post" action="/treinador/objetivos/<?= (int) $goalId ?>/remover">
	<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
	<button class="btn btn--sm btn--danger" type="submit" data-confirm="Remover da lista? Ele deixa de aparecer para todos os atletas; o histórico fica guardado.">Remover da lista</button>
</form>
<script src="<?= e(\Correon\View::asset('/assets/workout-form.js')) ?>" defer></script>
