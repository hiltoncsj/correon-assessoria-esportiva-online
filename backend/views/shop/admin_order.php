<?php /** @var array<string,mixed> $order @var list<array<string,mixed>> $items */
use Correon\Shop;
$action = '/admin/pedidos/' . (int) $order['id'];
$buttons = [];
if (!$order['cancelled_at']) {
	$buttons[] = $order['paid_at'] ? ['unpay', 'Desfazer pagamento', 'btn--ghost', 'Desfazer a confirmação de pagamento?'] : ['pay', 'Marcar como pago', '', 'Confirma que o valor foi recebido?'];
	if (!$order['delivered_at']) { $buttons[] = ['deliver', 'Marcar como entregue', 'btn--ghost', 'Confirma a entrega?']; }
	if (!$order['paid_at'] && !$order['delivered_at']) { $buttons[] = ['cancel', 'Cancelar pedido', 'btn--danger', 'Cancelar o pedido e devolver as peças ao estoque?']; }
}
?>
<p class="eyebrow"><a href="/admin/pedidos">← Pedidos</a></p>
<h1>Pedido #<?= (int) $order['id'] ?></h1>
<p><span class="badge"><?= e(Shop::state($order)) ?></span> <span class="muted"><?= e($order['athlete']) ?> · <?= e($order['email']) ?> · <?= e(Shop::METHODS[$order['payment_method']] ?? '') ?></span></p>
<section class="tile"><?php require __DIR__ . '/items.php'; ?></section>

<div class="actions">
	<?php foreach ($buttons as [$name, $label, $class, $confirm]): ?>
		<form method="post" action="<?= e($action) ?>">
			<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
			<input type="hidden" name="action" value="<?= $name ?>">
			<button class="btn btn--sm <?= $class ?>" type="submit" data-confirm="<?= e($confirm) ?>"><?= e($label) ?></button>
		</form>
	<?php endforeach; ?>
</div>

<?php if (!$order['cancelled_at'] && !$order['paid_at'] && $order['payment_method'] === 'card_link'): ?>
	<form class="tile form form--narrow" method="post" action="<?= e($action) ?>">
		<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
		<input type="hidden" name="action" value="link">
		<label class="field">Link de pagamento deste pedido (https://)<input type="url" name="payment_link" value="<?= e((string) $order['payment_link']) ?>" maxlength="500"></label>
		<button class="btn btn--sm" type="submit">Salvar link</button>
	</form>
<?php endif; ?>
<script src="<?= e(\Correon\View::asset('/assets/workout-form.js')) ?>" defer></script>
