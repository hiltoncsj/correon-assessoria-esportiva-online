<?php /** @var list<array<string,mixed>> $items objetivos de um atleta (somente leitura) */ ?>
<?php if (!$items): ?>
	<p class="muted">Não está em nenhum objetivo ou prova.</p>
<?php else: ?>
	<ul class="done-list">
		<?php foreach ($items as $g): ?>
			<li>
				<?php require __DIR__ . '/summary.php'; ?>
				<?php if ($g['target']): ?><span class="muted">Meta: <?= e($g['target']) ?></span><?php endif; ?>
				<?php if ($g['result']): ?><span class="muted">Resultado: <?= e($g['result']) ?></span><?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
