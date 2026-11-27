<?php /** @var string $from @var string $to @var array $sold @var array $received @var array $pending @var int $cancelled @var array $byItem @var array $byMethod @var array $stock */
use Correon\{Billing, Shop};
?>
<p class="eyebrow">Loja</p>
<h1>Relatório de vendas</h1>
<form class="tile form form--inline" method="get" action="/admin/vendas">
	<label class="field">De<input type="date" name="de" value="<?= e($from) ?>"></label>
	<label class="field">Até<input type="date" name="ate" value="<?= e($to) ?>"></label>
	<button class="btn btn--sm" type="submit">Filtrar</button>
</form>
<section class="tile" aria-labelledby="h-tot">
	<h2 id="h-tot">Totais</h2>
	<dl class="facts">
		<dt>Vendido no período</dt><dd><?= e(Billing::money($sold['total'])) ?> (<?= (int) $sold['n'] ?> pedido(s))</dd>
		<dt>Recebido no período</dt><dd><?= e(Billing::money($received['total'])) ?> (<?= (int) $received['n'] ?>)</dd>
		<dt>A receber (tudo em aberto)</dt><dd><?= e(Billing::money($pending['total'])) ?> (<?= (int) $pending['n'] ?>)</dd>
		<dt>Cancelados no período</dt><dd><?= $cancelled ?></dd>
		<?php foreach ($byMethod as $m): ?><dt><?= e(Shop::METHODS[$m['payment_method']] ?? $m['payment_method']) ?></dt><dd><?= e(Billing::money($m['total'])) ?> (<?= (int) $m['n'] ?>)</dd><?php endforeach; ?>
	</dl>
</section>
<section class="tile" aria-labelledby="h-item">
	<h2 id="h-item">Por produto e tamanho</h2>
	<?php if (!$byItem): ?><p class="muted">Nenhuma venda no período.</p><?php else: ?>
		<ul class="done-list">
			<?php foreach ($byItem as $r): ?><li><strong><?= e($r['product_name']) ?></strong> <span class="badge"><?= e($r['size']) ?></span> <span><?= (int) $r['qty'] ?> un. · <?= e(Billing::money($r['total'])) ?></span></li><?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
<section class="tile" aria-labelledby="h-stk">
	<h2 id="h-stk">Estoque atual</h2>
	<?php if (!$stock): ?><p class="muted">Nenhum produto visível.</p><?php else: ?>
		<ul class="done-list">
			<?php foreach ($stock as $r): ?><li><strong><?= e($r['name']) ?></strong> <span class="badge"><?= e($r['size']) ?></span> <span class="badge <?= (int) $r['stock'] === 0 ? 'badge--off' : '' ?>"><?= (int) $r['stock'] ?> un.</span></li><?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
