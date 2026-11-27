<?php /** @var list<array<string,mixed>> $items @var array<string,mixed> $order */
use Correon\Billing;
?>
<ul class="done-list">
	<?php foreach ($items as $i): ?>
		<li><strong><?= e($i['product_name']) ?></strong> <span class="badge"><?= e($i['size']) ?></span> <span><?= (int) $i['qty'] ?> × <?= e(Billing::money($i['unit_cents'])) ?></span></li>
	<?php endforeach; ?>
</ul>
<p><strong>Total: <?= e(Billing::money($order['total_cents'])) ?></strong></p>
