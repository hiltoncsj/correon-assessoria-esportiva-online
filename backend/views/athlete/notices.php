<?php /** @var array $rows */ ?>
<p class="eyebrow">Atleta</p>
<h1>Avisos</h1>
<?php if (!$rows): ?><div class="tile"><p class="muted">Nenhum aviso por enquanto.</p></div><?php else: ?>
	<ul class="agenda">
		<?php foreach ($rows as $n): $tag = $n['link'] ? 'a' : 'div'; ?>
			<li class="agenda__item">
				<a href="<?= e($n['link'] ?: '/app/avisos') ?>">
					<?php if ($n['read_at'] === null): ?><span class="badge badge--ok">Novo</span><?php endif; ?>
					<span class="agenda__title"><strong><?= e($n['title']) ?></strong><?= $n['body'] ? '<br>' . nl2br(e($n['body'])) : '' ?></span>
					<span class="muted"><?= e(date('d/m/Y H:i', strtotime($n['created_at']))) ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
