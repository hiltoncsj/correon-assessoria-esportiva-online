<?php /** @var list<string> $modalities */ ?>
<p class="eyebrow">Atleta</p>
<h1>Minhas modalidades</h1>
<?php if (!$modalities): ?>
	<div class="tile"><p class="muted">Você não tem modalidades com contrato vigente.</p></div>
<?php else: ?>
	<div class="grid">
		<?php foreach ($modalities as $m): ?><section class="tile"><h2><?= e($m) ?></h2><p><span class="badge badge--ok">Contrato vigente</span></p></section><?php endforeach; ?>
	</div>
<?php endif; ?>
