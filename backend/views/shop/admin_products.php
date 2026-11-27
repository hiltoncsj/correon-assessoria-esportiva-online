<?php /** @var list<array<string,mixed>> $products @var array<string,mixed> $settings */
use Correon\Billing;
?>
<p class="eyebrow">Administrador</p>
<h1>Loja</h1>
<p class="actions"><a class="btn btn--sm" href="/admin/loja/novo">+ Novo produto</a> <a class="btn btn--sm btn--ghost" href="/admin/pedidos">Pedidos</a> <a class="btn btn--sm btn--ghost" href="/admin/vendas">Relatório de vendas</a></p>

<section class="tile" aria-labelledby="h-prod">
	<h2 id="h-prod">Produtos</h2>
	<?php if (!$products): ?><p class="muted">Nenhum produto cadastrado.</p><?php else: ?>
		<ul class="done-list">
			<?php foreach ($products as $p): ?>
				<li>
					<a href="/admin/loja/<?= (int) $p['id'] ?>"><?= e($p['name']) ?></a>
					<span><?= e(Billing::money($p['price_cents'])) ?></span>
					<span class="muted"><?= e($p['sizes'] ?? 'sem tamanhos ativos') ?></span>
					<span class="badge <?= $p['active'] ? 'badge--ok' : 'badge--off' ?>"><?= $p['active'] ? 'Visível' : 'Oculto' ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>

<form class="tile form form--narrow" method="post" action="/admin/loja/pagamento" novalidate>
	<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
	<h2>Formas de pagamento</h2>
	<?php if (!$settings['pix_key'] && !$settings['card_link'] && !$settings['allow_on_delivery']): ?><p class="notice notice--err">Sem nenhuma forma ativa, os alunos não conseguem fazer pedidos.</p><?php endif; ?>
	<label class="field">Chave Pix<input type="text" name="pix_key" value="<?= e((string) $settings['pix_key']) ?>" maxlength="140" autocomplete="off"></label>
	<label class="field">Nome do recebedor<input type="text" name="pix_holder" value="<?= e((string) $settings['pix_holder']) ?>" maxlength="120"></label>
	<label class="field">Link de pagamento para cartão (https://)<input type="url" name="card_link" value="<?= e((string) $settings['card_link']) ?>" maxlength="500"></label>
	<label class="check"><input type="checkbox" name="allow_on_delivery" value="1"<?= $settings['allow_on_delivery'] ? ' checked' : '' ?>><span>Permitir pagar na entrega</span></label>
	<label class="field">Instruções de pagamento e entrega<textarea name="instructions" rows="4" maxlength="1000"><?= e((string) $settings['instructions']) ?></textarea></label>
	<p class="muted">Não há cobrança automática: confira o recebimento e marque o pedido como pago. Não informe dados de cartão aqui.</p>
	<button class="btn btn--sm" type="submit">Salvar</button>
</form>
