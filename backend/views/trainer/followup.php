<?php /** @var array $rows @var array $records @var int $weeks @var string $from */
use Correon\Workout;
?>
<p class="eyebrow">Treinador</p>
<h1>Acompanhamento</h1>
<nav class="monthnav" aria-label="Período">
	<?php foreach ([1 => 'Esta semana', 4 => '4 semanas', 12 => '12 semanas'] as $n => $label): ?>
		<a class="btn btn--sm <?= $weeks === $n ? '' : 'btn--ghost' ?>" href="/treinador/acompanhamento?semanas=<?= $n ?>"<?= $weeks === $n ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
	<?php endforeach; ?>
</nav>
<p class="muted">Treinos publicados de <?= e(date('d/m', strtotime($from))) ?> até hoje.</p>

<?php if (!$rows): ?>
	<div class="tile"><p class="muted">Nenhum treino publicado no período.</p></div>
<?php else: ?>
	<div class="tbl-wrap">
		<table class="tbl">
			<thead><tr><th>Atleta</th><th>Programados</th><th>Realizados</th><th>Em parte</th><th>Não fez</th><th>Sem registro</th><th>Esforço médio</th></tr></thead>
			<tbody>
			<?php foreach ($rows as $r): ?>
				<tr>
					<td><a href="/treinador/atletas/<?= (int) $r['id'] ?>"><?= e($r['name']) ?></a></td>
					<td><?= (int) $r['planned'] ?></td>
					<td><?= (int) $r['completed'] ?></td>
					<td><?= (int) $r['partial'] ?></td>
					<td><?= (int) $r['skipped'] ?></td>
					<td><?= (int) $r['missing'] ? '<span class="badge badge--off">' . (int) $r['missing'] . '</span>' : '0' ?></td>
					<td><?= $r['effort'] === null ? '—' : e(str_replace('.', ',', (string) $r['effort'])) ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>

<section class="tile" aria-labelledby="h-reg">
	<h2 id="h-reg">Registros recentes</h2>
	<?php if (!$records): ?>
		<p class="muted">Nenhum registro no período.</p>
	<?php else: ?>
		<ul class="plain-list">
			<?php foreach ($records as $r): ?>
				<li>
					<a href="/treinador/treinos/<?= (int) $r['id'] ?>"><?= e($r['athlete']) ?> — <?= e($r['title']) ?></a>
					<span class="muted"><?= e(\Correon\View::dateBr($r['performed_on'])) ?> · <?= e(Workout::OUTCOMES[$r['outcome']]) ?><?= $r['effort'] !== null ? ' · esforço ' . (int) $r['effort'] . '/10' : '' ?></span>
					<?php if ($r['notes']): ?><br><span>“<?= e($r['notes']) ?>”</span><?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
