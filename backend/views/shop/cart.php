<?php /** @var array{lines:array,total:int} $cart @var array<string,string> $methods */
use Correon\Billing;
?>
<p class="eyebrow"><a href="<?= e($base) ?>/loja">← Loja</a></p>
<h1>Carrinho</h1>
<?php if (!$cart['lines']): ?>
	<div class="tile"><p class="muted">O carrinho está vazio.</p></div>
<?php else: ?>
	<section class="tile">
		<ul class="done-list">
			<?php foreach ($cart['lines'] as $l): ?>
				<li>
					<strong><?= e($l['name']) ?></strong> <span class="badge"><?= e($l['size']) ?></span>
					<span><?= (int) $l['qty'] ?> × <?= e(Billing::money($l['price_cents'])) ?> = <?= e(Billing::money($l['subtotal'])) ?></span>
					<form method="post" action="<?= e($base) ?>/loja/carrinho/remover">
						<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
						<input type="hidden" name="variant_id" value="<?= (int) $l['id'] ?>">
						<button class="btn btn--sm btn--ghost" type="submit">Tirar</button>
					</form>
				</li>
			<?php endforeach; ?>
		</ul>
		<p><strong>Total: <?= e(Billing::money($cart['total'])) ?></strong></p>
	</section>
	<?php if (!$methods): ?>
		<p class="notice">A loja ainda não está recebendo pedidos: as formas de pagamento não foram configuradas.</p>
	<?php else: ?>
		<form class="tile form form--narrow" method="post" action="<?= e($base) ?>/loja/pedido">
			<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
			<input type="hidden" name="total" value="<?= (int) $cart['total'] ?>">
			<label class="field">Forma de pagamento
				<select name="method">
					<?php foreach ($methods as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?>
				</select>
			</label>
			<button class="btn" type="submit">Fazer pedido</button>
		</form>
	<?php endif; ?>
<?php endif; ?>
