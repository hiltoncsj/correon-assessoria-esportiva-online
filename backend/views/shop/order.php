<?php /** @var array<string,mixed> $order @var list<array<string,mixed>> $items @var array<string,mixed> $settings */
use Correon\Shop;
$open = !$order['paid_at'] && !$order['cancelled_at'];
$link = $order['payment_link'] ?: $settings['card_link'];
?>
<p class="eyebrow"><a href="<?= e($base) ?>/pedidos">← Meus pedidos</a></p>
<h1>Pedido #<?= (int) $order['id'] ?></h1>
<p><span class="badge"><?= e(Shop::state($order)) ?></span> <span class="muted"><?= e(Shop::METHODS[$order['payment_method']] ?? '') ?></span></p>
<section class="tile"><?php require __DIR__ . '/items.php'; ?></section>

<?php if ($open): ?>
	<section class="tile" aria-labelledby="h-pay">
		<h2 id="h-pay">Como pagar</h2>
		<?php if ($order['payment_method'] === 'pix' && $settings['pix_key']): ?>
			<dl class="facts">
				<dt>Chave Pix</dt><dd><?= e($settings['pix_key']) ?></dd>
				<?php if ($settings['pix_holder']): ?><dt>Recebedor</dt><dd><?= e($settings['pix_holder']) ?></dd><?php endif; ?>
			</dl>
		<?php elseif ($order['payment_method'] === 'card_link'): ?>
			<?php if ($link): ?>
				<p><a class="btn btn--sm" href="<?= e($link) ?>" target="_blank" rel="noopener noreferrer">Abrir link de pagamento</a></p>
				<p class="muted">Confira o valor antes de pagar. Informe o número do pedido à equipe.</p>
			<?php else: ?>
				<p>A equipe vai enviar o link de pagamento deste pedido.</p>
			<?php endif; ?>
		<?php else: ?>
			<p>Pague ao receber os produtos.</p>
		<?php endif; ?>
		<?php if ($settings['instructions']): ?><p><?= nl2br(e($settings['instructions'])) ?></p><?php endif; ?>
		<p class="muted">O pedido aparece como pago depois que a equipe confirmar o recebimento.</p>
	</section>
	<?php if (!$order['delivered_at']): ?>
		<form method="post" action="<?= e($base) ?>/pedidos/<?= (int) $order['id'] ?>/cancelar">
			<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
			<button class="btn btn--sm btn--danger" type="submit">Cancelar pedido</button>
		</form>
	<?php endif; ?>
<?php endif; ?>
