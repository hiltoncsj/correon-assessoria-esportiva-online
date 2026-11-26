<?php /** @var list<array<string,mixed>> $rows */
use Correon\{Goals, View};
?>
<p class="eyebrow">Treinador</p>
<h1>Objetivos e provas</h1>
<p class="muted">Próximos dos seus atletas. Para cadastrar, abra o atleta em <a href="/treinador/atletas">Meus atletas</a>.</p>
<section class="tile">
	<?php if (!$rows): ?><p class="muted">Nenhum objetivo ou prova planejado.</p><?php else: ?>
		<ul class="done-list">
			<?php foreach ($rows as $g): ?>
				<li>
					<strong><?= e($g['target_date'] ? View::dateBr($g['target_date']) : 'Sem data') ?></strong>
					<a href="/treinador/atletas/<?= (int) $g['athlete_id'] ?>/objetivos"><?= e($g['athlete']) ?></a>
					<span><?= e($g['name']) ?></span>
					<span class="badge"><?= e(Goals::SPORTS[$g['sport']] ?? $g['sport']) ?></span>
					<span class="muted"><?= e(implode(' · ', array_filter([$g['distance'], $g['city']]))) ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
