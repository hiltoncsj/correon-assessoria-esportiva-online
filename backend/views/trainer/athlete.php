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
	</p>
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
