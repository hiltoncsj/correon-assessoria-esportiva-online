<?php /** @var array{next:array,past:array} $goals @var array<string,string> $old @var ?string $error */ ?>
<p class="eyebrow">Treinador</p>
<h1>Objetivos e provas</h1>
<p class="muted">Lista única da equipe. Os atletas colocam o próprio nome.</p>

<details class="tile newbox"<?= $error ? ' open' : '' ?>>
	<summary>+ Novo objetivo ou prova</summary>
	<form class="form" method="post" action="/treinador/objetivos" novalidate>
		<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
		<?php if ($error): ?><p class="notice notice--err" role="alert"><?= e($error) ?></p><?php endif; ?>
		<?php require __DIR__ . '/fields.php'; ?>
		<div class="actions"><button class="btn btn--sm" type="submit">Cadastrar</button></div>
	</form>
</details>

<?php foreach (['next' => 'Próximos', 'past' => 'Anteriores'] as $key => $heading): ?>
	<?php if ($key === 'past' && !$goals[$key]) { continue; } ?>
	<section class="tile" aria-labelledby="h-<?= $key ?>">
		<h2 id="h-<?= $key ?>"><?= $heading ?></h2>
		<?php if (!$goals[$key]): ?><p class="muted">Nada cadastrado.</p><?php else: ?>
			<ul class="goal-list">
				<?php foreach ($goals[$key] as $g): ?>
					<li>
						<?php require __DIR__ . '/summary.php'; ?>
						<p class="actions"><a class="btn btn--sm btn--ghost" href="/treinador/objetivos/<?= (int) $g['id'] ?>">Abrir / editar</a></p>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
<?php endforeach; ?>
