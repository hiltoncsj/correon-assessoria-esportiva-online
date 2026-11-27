<?php /** @var list<array<string,mixed>> $products @var int $inCart */
use Correon\Billing;
?>
<p class="eyebrow"><?= e($label) ?></p>
<h1>Loja</h1>
<p class="actions"><a class="btn btn--sm" href="<?= e($base) ?>/loja/carrinho">Ver carrinho (<?= (int) $inCart ?>)</a> <a class="btn btn--sm btn--ghost" href="<?= e($base) ?>/pedidos">Meus pedidos</a></p>

<?php if (!$products): ?>
	<div class="tile"><p class="muted">Nenhum produto disponível no momento.</p></div>
<?php else: ?>
	<div class="grid">
		<?php foreach ($products as $p): $available = array_filter($p['variants'], static fn ($v) => (int) $v['stock'] > 0); ?>
			<article class="tile">
				<?php if ($p['images']): ?>
					<div class="shop-gallery" tabindex="0" role="group" aria-label="Fotos de <?= e($p['name']) ?>">
						<?php foreach ($p['images'] as $n => $imageId): ?><a class="shop-gallery__item" href="<?= e($base) ?>/loja/imagem/<?= (int) $imageId ?>" data-lightbox title="Ver em tela cheia"><img class="shop-photo" src="<?= e($base) ?>/loja/imagem/<?= (int) $imageId ?>" alt="<?= e($p['name']) ?>, foto <?= $n + 1 ?>" loading="lazy"></a><?php endforeach; ?>
					</div>
					<?php if (count($p['images']) > 1): ?><p class="muted"><?= count($p['images']) ?> fotos: arraste para o lado.</p><?php endif; ?>
				<?php endif; ?>
				<h2><?= e($p['name']) ?></h2>
				<p><strong><?= e(Billing::money($p['price_cents'])) ?></strong></p>
				<?php if ($p['description']): ?><p class="muted"><?= nl2br(e($p['description'])) ?></p><?php endif; ?>
				<p class="muted"><?php foreach ($p['variants'] as $v): ?><span class="badge<?= (int) $v['stock'] > 0 ? '' : ' badge--off' ?>"><?= e($v['size']) ?>: <?= (int) $v['stock'] > 0 ? (int) $v['stock'] . ' disp.' : 'esgotado' ?></span> <?php endforeach; ?></p>
				<?php if ($available): ?>
					<form class="form" method="post" action="<?= e($base) ?>/loja/carrinho">
						<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
						<div class="row">
							<label class="field">Tamanho
								<select name="variant_id">
									<?php foreach ($available as $v): ?><option value="<?= (int) $v['id'] ?>"><?= e($v['size']) ?></option><?php endforeach; ?>
								</select>
							</label>
							<label class="field">Quantidade<input type="number" name="qty" value="1" min="1" max="10"></label>
						</div>
						<button class="btn btn--sm" type="submit">Adicionar ao carrinho</button>
					</form>
				<?php else: ?>
					<p><span class="badge badge--off">Esgotado</span></p>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
<script src="<?= e(\Correon\View::asset('/assets/lightbox.js')) ?>" defer></script>
