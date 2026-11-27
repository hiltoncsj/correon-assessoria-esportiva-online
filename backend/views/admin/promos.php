<?php /** @var list<array<string,mixed>> $rows @var int $liveId */
use Correon\Promo;
$when = static fn (string $d) => date('d/m/Y H:i', strtotime($d));
?>
<p class="eyebrow">Administrador</p>
<h1>Promoção</h1>
<p class="muted">Faixa com cronômetro e destaque com imagem ou vídeo, no topo da página principal, somente durante o período. Horário de Brasília.</p>
<p class="actions"><a class="btn btn--sm" href="/admin/promocao/novo">+ Nova promoção</a></p>
<section class="tile">
	<?php if (!$rows): ?><p class="muted">Nenhuma promoção cadastrada.</p><?php else: ?>
		<ul class="done-list">
			<?php foreach ($rows as $p): $state = Promo::state($p); ?>
				<li>
					<a href="/admin/promocao/<?= (int) $p['id'] ?>"><?= e($p['title']) ?></a>
					<span class="muted"><?= e($when($p['starts_at'])) ?> até <?= e($when($p['ends_at'])) ?></span>
					<?php if ($p['annual_discount_pct'] !== null): ?><span class="muted"><?= e(Promo::pctLabel($p['annual_discount_pct'])) ?>% no anual</span><?php endif; ?>
					<span class="badge <?= $state === 'No ar' ? 'badge--ok' : ($state === 'Agendada' ? 'badge--draft' : 'badge--off') ?>"><?= e($state) ?></span>
					<?php if ($state === 'No ar' && (int) $p['id'] !== $liveId): ?><span class="muted">(outra, mais recente, está sendo exibida)</span><?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
