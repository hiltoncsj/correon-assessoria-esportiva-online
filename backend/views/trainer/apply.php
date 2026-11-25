<?php /** @var list<string> $mods @var string $mod @var list<array<string,mixed>> $templates @var list<array<string,mixed>> $pending @var list<array<string,mixed>> $done @var string $monday @var string $sunday @var string $prev @var string $next */
$modNames = ['corrida' => 'Corrida', 'triathlon' => 'Triathlon', 'musculacao' => 'Musculação'];
?>
<p class="eyebrow">Treinador</p>
<h1>Aplicar modelos</h1>
<p class="muted">Passo 1: escolha a semana e marque os atletas de cada treino. No passo 2 você define em qual dia cada atleta faz cada sessão. Quem já recebeu treino na semana sai da lista, e ficam só os atletas que ainda não têm treino. Cada atleta recebe uma cópia da semana, que você pode ajustar individualmente.</p>

<?php if (count($mods) > 1): ?>
	<nav class="monthnav" aria-label="Modalidade">
		<?php foreach ($mods as $m): ?>
			<a class="btn btn--sm <?= $m === $mod ? '' : 'btn--ghost' ?>" href="/treinador/modelos/aplicar?modalidade=<?= e($m) ?>"<?= $m === $mod ? ' aria-current="page"' : '' ?>><?= e($modNames[$m] ?? $m) ?></a>
		<?php endforeach; ?>
	</nav>
<?php endif; ?>

<?php $fmt = static fn (string $d) => date('d/m', strtotime($d)); ?>
<?php if (!$mods): ?>
	<div class="tile"><p class="muted">Você ainda não tem atletas vinculados.</p></div>
<?php elseif (!$templates): ?>
	<div class="tile">
		<p class="muted">Você ainda não tem modelos de <?= e($modNames[$mod] ?? $mod) ?> com pelo menos uma sessão.</p>
		<p><a class="btn btn--sm" href="/treinador/modelos/novo?modalidade=<?= e($mod) ?>">Criar modelo</a></p>
	</div>
<?php else: ?>
	<nav class="monthnav" aria-label="Semana">
		<a class="btn btn--sm btn--ghost" href="/treinador/modelos/aplicar?modalidade=<?= e($mod) ?>&amp;semana=<?= e($prev) ?>">← Semana anterior</a>
		<strong>Semana de <?= e($fmt($monday)) ?> a <?= e($fmt($sunday)) ?></strong>
		<a class="btn btn--sm btn--ghost" href="/treinador/modelos/aplicar?modalidade=<?= e($mod) ?>&amp;semana=<?= e($next) ?>">Próxima semana →</a>
	</nav>
	<p class="muted"><?= count($pending) ?> atleta(s) sem treino nesta semana · <?= count($done) ?> já com treino.</p>

	<?php if (!$pending): ?>
		<div class="tile"><p><strong>Todos os atletas já têm treino nesta semana.</strong></p><p class="muted">Use a semana seguinte ou abra um atleta para ajustar o treino dele.</p></div>
	<?php else: ?>
	<form class="tile form form--wide" method="post" action="/treinador/modelos/montar" novalidate>
		<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
		<input type="hidden" name="modality" value="<?= e($mod) ?>">
		<input type="hidden" name="scheduled_date" value="<?= e($monday) ?>">
		<p class="muted">Marque, em cada treino, os atletas que o recebem. Um atleta só pode estar em um treino por semana.</p>
		<div class="grid grid--apply">
			<?php foreach ($templates as $t): ?>
				<fieldset class="tplbox">
					<legend><?= e($t['name']) ?></legend>
					<?php if ($t['level_description']): ?><p class="muted"><?= e($t['level_description']) ?></p><?php endif; ?>
					<?php foreach ($pending as $a): ?>
						<label class="check">
							<input type="checkbox" name="tpl[<?= (int) $t['id'] ?>][]" value="<?= (int) $a['id'] ?>" data-athlete="<?= (int) $a['id'] ?>"<?= (int) $a['default_template_id'] === (int) $t['id'] ? ' checked' : '' ?>>
							<span><?= e($a['name']) ?><?php if ((int) $a['zone_count'] === 0): ?> <a href="/treinador/atletas/<?= (int) $a['id'] ?>" class="badge badge--off">sem zonas de FC</a><?php endif; ?></span>
						</label>
					<?php endforeach; ?>
				</fieldset>
			<?php endforeach; ?>
		</div>
		<div class="actions">
			<button class="btn" type="submit">Montar a semana →</button>
			<a class="btn btn--ghost" href="/treinador/modelos">Gerenciar modelos</a>
		</div>
		<p class="muted">Atletas sem zonas de FC configuradas recebem o treino normalmente, mas verão apenas “Z1, Z2…” sem os bpm. Configure as zonas na página do atleta.</p>
	</form>
	<?php endif; ?>

	<?php if ($done): ?>
		<section class="tile" aria-labelledby="h-done">
			<h2 id="h-done">Já com treino nesta semana</h2>
			<ul class="done-list">
				<?php foreach ($done as $a): ?>
					<li>
						<a href="/treinador/atletas/<?= (int) $a['id'] ?>"><?= e($a['name']) ?></a>
						<?php if (str_contains((string) $a['week_status'], 'published')): ?><span class="badge badge--ok">Enviado</span><?php endif; ?>
						<?php if (str_contains((string) $a['week_status'], 'draft')): ?><span class="badge badge--draft">Rascunho</span><?php endif; ?>
						<span class="muted"><?= (int) $a['removable'] + (int) $a['logged'] ?> treino(s)<?= (int) $a['logged'] ? ' · ' . (int) $a['logged'] . ' já registrado(s)' : '' ?></span>
						<?php if ((int) $a['removable'] > 0): ?>
							<form method="post" action="/treinador/modelos/excluir">
								<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
								<input type="hidden" name="modality" value="<?= e($mod) ?>">
								<input type="hidden" name="scheduled_date" value="<?= e($monday) ?>">
								<input type="hidden" name="athlete_id" value="<?= (int) $a['id'] ?>">
								<button class="btn btn--sm btn--danger" type="submit" data-confirm="Excluir os treinos de <?= e($a['name']) ?> (<?= e($modNames[$mod] ?? $mod) ?>) desta semana? Eles somem para o atleta." aria-label="Excluir treino de <?= e($a['name']) ?>">Excluir treino</button>
							</form>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>
<?php endif; ?>
<script src="<?= e(\Correon\View::asset('/assets/workout-form.js')) ?>" defer></script>
