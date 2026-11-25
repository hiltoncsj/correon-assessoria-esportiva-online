<?php /** @var array $athlete @var list<string> $mods @var list<array<string,mixed>> $workouts */
$modNames = ['corrida' => 'Corrida', 'triathlon' => 'Triathlon', 'musculacao' => 'Musculação'];
$badge = ['draft' => 'badge--draft', 'published' => 'badge--ok', 'cancelled' => 'badge--off'];
?>
<p class="eyebrow"><a href="/treinador">← Meus atletas</a></p>
<h1><?= e($athlete['name']) ?></h1>

<section class="tile" aria-labelledby="novo">
	<h2 id="novo">Novo treino</h2>
	<p class="actions">
		<?php foreach ($mods as $m): ?>
			<a class="btn btn--sm" href="/treinador/treinos/novo?atleta=<?= (int) $athlete['id'] ?>&amp;modalidade=<?= e($m) ?>">+ <?= e($modNames[$m] ?? $m) ?></a>
		<?php endforeach; ?>
		<a class="btn btn--sm btn--ghost" href="/treinador/modelos/aplicar">Aplicar um modelo</a>
	</p>
</section>

<section class="tile" id="nivel" aria-labelledby="h-nivel">
	<h2 id="h-nivel">Treino padrão (nível)</h2>
	<p class="muted">O treino padrão já vem selecionado ao aplicar a semana. Escolha o modelo (Treino 1, 2, 3…) que corresponde ao nível deste atleta.</p>
	<?php foreach ($levels as $lv): ?>
		<form class="form form--inline" method="post" action="/treinador/atletas/<?= (int) $athlete['id'] ?>/nivel">
			<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
			<input type="hidden" name="modality" value="<?= e($lv['slug']) ?>">
			<label class="field"><?= e($lv['name']) ?>
				<select name="template_id" class="select">
					<option value="">— sem treino padrão —</option>
					<?php foreach ($lv['templates'] as $t): ?>
						<option value="<?= (int) $t['id'] ?>"<?= (int) $lv['default_template_id'] === (int) $t['id'] ? ' selected' : '' ?>><?= e($t['name']) ?><?= $t['level_description'] ? ' — ' . e($t['level_description']) : '' ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<button class="btn btn--sm" type="submit">Salvar</button>
		</form>
	<?php endforeach; ?>
</section>

<section class="tile" id="zonas" aria-labelledby="h-zonas">
	<h2 id="h-zonas">Zonas de frequência cardíaca</h2>
	<p class="muted">Nos treinos você escolhe só a zona (Z1 a Z7); o atleta vê os bpm dele. Informe a FC máxima e clique em “Calcular pelo padrão” para preencher as zonas pelos percentuais padrão, depois ajuste individualmente o que precisar e salve.</p>
	<form class="form" method="post" action="/treinador/atletas/<?= (int) $athlete['id'] ?>/zonas" novalidate>
		<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
		<label class="field">FC máxima (bpm)
			<input type="text" inputmode="numeric" name="max_hr" value="<?= $maxHr !== null ? (int) $maxHr : '' ?>" maxlength="3" class="zones__max" placeholder="ex.: 190">
		</label>
		<div class="zones">
			<?php for ($z = \Correon\Workout::ZONES; $z >= 1; $z--): [$lo, $hi] = \Correon\Workout::DEFAULT_ZONE_PCT[$z]; ?>
				<div class="zones__row">
					<strong>Zona <?= $z ?></strong>
					<label class="zones__f"><span class="visually-hidden">Zona <?= $z ?> mínimo (bpm)</span>
						<input type="text" inputmode="numeric" name="zones[<?= $z ?>][min]" value="<?= isset($zones[$z]) ? (int) $zones[$z]['min'] : '' ?>" maxlength="3" placeholder="mín"></label>
					<span aria-hidden="true">a</span>
					<label class="zones__f"><span class="visually-hidden">Zona <?= $z ?> máximo (bpm)</span>
						<input type="text" inputmode="numeric" name="zones[<?= $z ?>][max]" value="<?= isset($zones[$z]) ? (int) $zones[$z]['max'] : '' ?>" maxlength="3" placeholder="máx"></label>
					<span class="muted">bpm · padrão <?= $lo ?>–<?= $hi ?>% da FCmáx</span>
				</div>
			<?php endfor; ?>
		</div>
		<div class="actions">
			<button class="btn btn--sm" type="submit" name="action" value="save">Salvar zonas</button>
			<button class="btn btn--sm btn--ghost" type="submit" name="action" value="defaults" data-confirm="Calcular as zonas pelos percentuais padrão? Isso substitui as faixas atuais deste atleta.">Calcular pelo padrão</button>
		</div>
	</form>
	<script src="/assets/workout-form.js" defer></script>
</section>

<h2>Treinos</h2>
<?php if (!$workouts): ?>
	<div class="tile"><p class="muted">Nenhum treino prescrito ainda.</p></div>
<?php else: ?>
	<div class="tbl-wrap">
		<table class="tbl">
			<thead><tr><th>Data</th><th>Treino</th><th>Modalidade</th><th>Situação</th><th>Atleta</th><th></th></tr></thead>
			<tbody>
			<?php foreach ($workouts as $w): ?>
				<tr>
					<td><?= e(\Correon\View::dateBr($w['scheduled_date'])) ?></td>
					<td><?= e($w['title']) ?></td>
					<td><?= e($w['modality']) ?><?= \Correon\Workout::DISCIPLINES[$w['discipline']] !== $w['modality'] ? ' · ' . e(\Correon\Workout::DISCIPLINES[$w['discipline']]) : '' ?></td>
					<td><span class="badge <?= e($badge[$w['status']]) ?>"><?= e(\Correon\Workout::STATUS[$w['status']]) ?></span></td>
					<td><?= $w['outcome'] ? e(\Correon\Workout::OUTCOMES[$w['outcome']]) : '—' ?></td>
					<td><a class="btn btn--sm btn--ghost" href="/treinador/treinos/<?= (int) $w['id'] ?>">Abrir</a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
