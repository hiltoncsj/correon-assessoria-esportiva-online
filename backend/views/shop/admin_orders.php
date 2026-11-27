<?php /** @var list<array<string,mixed>> $orders @var string $filter */
use Correon\{Billing, Shop, View};
?>
<p class="eyebrow">Loja</p>
<h1>Pedidos</h1>
<p class="actions">
	<?php foreach (['abertos' => 'Em aberto', 'concluidos' => 'Concluídos', 'cancelados' => 'Cancelados', 'todos' => 'Todos'] as $key => $label): ?>
		<a class="btn btn--sm<?= $filter === $key ? '' : ' btn--ghost' ?>" href="/admin/pedidos?filtro=<?= $key ?>"><?= $label ?></a>
	<?php endforeach; ?>
</p>
<section class="tile">
	<?php if (!$orders): ?><p class="muted">Nenhum pedido.</p><?php else: ?>
		<ul class="done-list">
			<?php foreach ($orders as $o): ?>
				<li>
					<a href="/admin/pedidos/<?= (int) $o['id'] ?>">Pedido #<?= (int) $o['id'] ?></a>
					<span><?= e($o['athlete']) ?></span>
					<span><?= e(View::dateBr(substr($o['created_at'], 0, 10))) ?> · <?= e(Billing::money($o['total_cents'])) ?> · <?= e(Shop::METHODS[$o['payment_method']] ?? '') ?></span>
					<span class="badge"><?= e(Shop::state($o)) ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
