<?php /** @var list<array<string,mixed>> $items @var ?string $base endereço de edição; null = somente leitura */
use Correon\{Goals, View};
?>
<ul class="done-list">
	<?php foreach ($items as $g): ?>
		<li>
			<strong><?= e($g['name']) ?></strong>
			<span class="badge"><?= e(Goals::KINDS[$g['kind']] ?? $g['kind']) ?></span>
			<span class="badge"><?= e(Goals::SPORTS[$g['sport']] ?? $g['sport']) ?></span>
			<span class="badge <?= $g['status'] === 'done' ? 'badge--ok' : ($g['status'] === 'cancelled' ? 'badge--off' : 'badge--draft') ?>"><?= e(Goals::STATUSES[$g['status']] ?? $g['status']) ?></span>
			<span class="muted"><?= e(implode(' · ', array_filter([
				$g['target_date'] ? View::dateBr($g['target_date']) : null, $g['distance'], $g['city'],
				$g['target'] ? 'Meta: ' . $g['target'] : null, $g['result'] ? 'Resultado: ' . $g['result'] : null,
			]))) ?></span>
			<?php if ($base !== null): ?><a href="<?= e($base) ?>/<?= (int) $g['id'] ?>">Editar</a><?php endif; ?>
		</li>
	<?php endforeach; ?>
</ul>
