<?php /** @var list<string> $mods @var string $mod @var list<array<string,mixed>> $templates @var list<array<string,mixed>> $athletes @var string $monday */
$modNames = ['corrida' => 'Corrida', 'triathlon' => 'Triathlon', 'musculacao' => 'Musculação'];
?>
<p class="eyebrow">Treinador</p>
<h1>Aplicar modelos</h1>
<p class="muted">Escolha a semana e, para cada atleta, o treino (nível) que ele recebe. O treino padrão de cada atleta já vem marcado. Cada atleta recebe uma cópia da semana, que você pode ajustar individualmente.</p>

<?php if (count($mods) > 1): ?>
	<nav class="monthnav" aria-label="Modalidade">
		<?php foreach ($mods as $m): ?>
			<a class="btn btn--sm <?= $m === $mod ? '' : 'btn--ghost' ?>" href="/treinador/modelos/aplicar?modalidade=<?= e($m) ?>"<?= $m === $mod ? ' aria-current="page"' : '' ?>><?= e($modNames[$m] ?? $m) ?></a>
		<?php endforeach; ?>
	</nav>
<?php endif; ?>

<?php if (!$mods): ?>
	<div class="tile"><p class="muted">Você ainda não tem atletas vinculados.</p></div>
<?php elseif (!$templates): ?>
	<div class="tile">
		<p class="muted">Você ainda não tem modelos de <?= e($modNames[$mod] ?? $mod) ?> com pelo menos uma sessão.</p>
		<p><a class="btn btn--sm" href="/treinador/modelos/novo?modalidade=<?= e($mod) ?>">Criar modelo</a></p>
	</div>
<?php elseif (!$athletes): ?>
	<div class="tile"><p class="muted">Nenhum atleta vinculado a você nesta modalidade.</p></div>
<?php else: ?>
	<form class="tile form form--wide" method="post" action="/treinador/modelos/aplicar" novalidate>
		<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
		<input type="hidden" name="modality" value="<?= e($mod) ?>">
		<div class="row">
			<label class="field">Semana de (escolha qualquer dia; vale a semana de segunda a domingo)
				<input type="date" name="scheduled_date" value="<?= e($monday) ?>" required>
			</label>
			<label class="check">
				<input type="checkbox" name="publish" value="1" checked>
				<span>Publicar já para os atletas (desmarque para salvar como rascunho)</span>
			</label>
		</div>

		<p class="muted">Marque, em cada treino, os atletas que o recebem. Um atleta só pode estar em um treino por semana.</p>
		<div class="grid grid--apply">
			<?php foreach ($templates as $t): ?>
				<fieldset class="tplbox">
					<legend><?= e($t['name']) ?></legend>
					<?php if ($t['level_description']): ?><p class="muted"><?= e($t['level_description']) ?></p><?php endif; ?>
					<?php foreach ($athletes as $a): ?>
						<label class="check">
							<input type="checkbox" name="tpl[<?= (int) $t['id'] ?>][]" value="<?= (int) $a['id'] ?>" data-athlete="<?= (int) $a['id'] ?>"<?= (int) $a['default_template_id'] === (int) $t['id'] ? ' checked' : '' ?>>
							<span><?= e($a['name']) ?><?php if ((int) $a['zone_count'] === 0): ?> <a href="/treinador/atletas/<?= (int) $a['id'] ?>" class="badge badge--off">sem zonas de FC</a><?php endif; ?></span>
						</label>
					<?php endforeach; ?>
				</fieldset>
			<?php endforeach; ?>
		</div>
		<div class="actions">
			<button class="btn" type="submit">Aplicar semana</button>
			<a class="btn btn--ghost" href="/treinador/modelos">Gerenciar modelos</a>
		</div>
		<p class="muted">Atletas sem zonas de FC configuradas recebem o treino normalmente, mas verão apenas “Z1, Z2…” sem os bpm. Configure as zonas na página do atleta.</p>
	</form>
<?php endif; ?>
<script src="/assets/workout-form.js" defer></script>
