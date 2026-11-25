<?php /** @var list<array<string,mixed>> $rows @var string $monthLabel @var string $prev @var string $next */
use Correon\Workout;
?>
<p class="eyebrow">Atleta</p>
<h1>Meus treinos</h1>
<nav class="monthnav" aria-label="Mês">
	<a class="btn btn--sm btn--ghost" href="/app/treinos?mes=<?= e($prev) ?>">← Anterior</a>
	<strong><?= e(ucfirst($monthLabel)) ?></strong>
	<a class="btn btn--sm btn--ghost" href="/app/treinos?mes=<?= e($next) ?>">Próximo →</a>
</nav>
<?php if (!$rows): ?>
	<div class="tile"><p class="muted">Nenhum treino publicado neste mês.</p></div>
<?php else: ?>
	<ul class="agenda">
		<?php foreach ($rows as $r): ?>
			<li class="agenda__item">
				<a href="/app/treinos/<?= (int) $r['id'] ?>">
					<span class="agenda__date"><?= e(\Correon\View::dateBr($r['scheduled_date'])) ?></span>
					<span class="agenda__title"><?= e($r['title']) ?></span>
					<span class="muted"><?= e($r['modality']) ?><?= Workout::DISCIPLINES[$r['discipline']] !== $r['modality'] ? ' · ' . e(Workout::DISCIPLINES[$r['discipline']]) : '' ?></span>
					<?php if ($r['outcome']): ?>
						<span class="badge badge--ok"><?= e(Workout::OUTCOMES[$r['outcome']]) ?></span>
					<?php elseif ($r['scheduled_date'] < date('Y-m-d')): ?>
						<span class="badge badge--draft">Sem registro</span>
					<?php else: ?>
						<span class="badge">Programado</span>
					<?php endif; ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
