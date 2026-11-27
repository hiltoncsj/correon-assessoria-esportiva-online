<?php /** @var list<array<string,mixed>> $items objetivos de um atleta (somente leitura) */ ?>
<?php if (!$items): ?>
	<p class="muted">Não está em nenhum objetivo ou prova.</p>
<?php else: ?>
	<ul class="goal-list">
		<?php foreach ($items as $g): ?>
			<li>
				<?php $showPersonal = true; require __DIR__ . '/summary.php'; ?>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
