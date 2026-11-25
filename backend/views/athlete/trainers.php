<?php /** @var list<array<string,mixed>> $links */ ?>
<p class="eyebrow">Atleta</p>
<h1>Meu treinador</h1>
<?php if (!$links): ?>
	<div class="tile"><p class="muted">Você ainda não tem treinador atribuído. A CorreON fará essa atribuição.</p></div>
<?php else: ?>
	<div class="grid">
		<?php foreach ($links as $l): ?>
			<section class="tile">
				<p class="eyebrow"><?= e($l['modality']) ?></p>
				<h2><?= e($l['trainer']) ?></h2>
			</section>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
