<?php /** @var array $items @var ?string $seen */
use Correon\Workout;
?>
<p class="eyebrow">Treinador</p>
<h1>Novidades</h1>
<p class="muted">Treinos registrados pelos seus atletas nos últimos 30 dias.</p>
<?php if (!$items): ?>
	<div class="tile"><p class="muted">Nenhum registro ainda.</p></div>
<?php else: ?>
	<ul class="agenda">
		<?php foreach ($items as $r): $isNew = $seen === null || $r['updated_at'] > $seen; ?>
			<li class="agenda__item">
				<a href="/treinador/treinos/<?= (int) $r['id'] ?>">
					<?php if ($isNew): ?><span class="badge badge--ok">Novo</span><?php endif; ?>
					<span class="agenda__date"><?= e($r['athlete']) ?></span>
					<span class="agenda__title"><?= e($r['title']) ?><?= $r['notes'] ? ' — “' . e($r['notes']) . '”' : '' ?></span>
					<span class="muted"><?= e(\Correon\View::dateBr($r['performed_on'])) ?> · <?= e(Workout::OUTCOMES[$r['outcome']]) ?><?= $r['effort'] !== null ? ' · ' . (int) $r['effort'] . '/10' : '' ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
