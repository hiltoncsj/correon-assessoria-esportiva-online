<?php /** @var list<array<string,mixed>> $orders */
use Correon\{Billing, Shop, View};
?>
<p class="eyebrow"><a href="<?= e($base) ?>/loja">← Loja</a></p>
<h1>Meus pedidos</h1>
<section class="tile">
	<?php if (!$orders): ?><p class="muted">Você ainda não fez pedidos.</p><?php else: ?>
		<ul class="done-list">
			<?php foreach ($orders as $o): ?>
				<li>
					<a href="<?= e($base) ?>/pedidos/<?= (int) $o['id'] ?>">Pedido #<?= (int) $o['id'] ?></a>
					<span><?= e(View::dateBr(substr($o['created_at'], 0, 10))) ?> · <?= e(Billing::money($o['total_cents'])) ?></span>
					<span class="badge"><?= e(Shop::state($o)) ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
