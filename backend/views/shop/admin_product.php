<?php /** @var ?int $id @var array<string,mixed> $old @var ?string $error @var list<array<string,mixed>> $variants @var int $blankRows @var list<array<string,mixed>> $images @var int $maxImages */ ?>
<p class="eyebrow"><a href="/admin/loja">← Loja</a></p>
<h1><?= $id ? 'Editar produto' : 'Novo produto' ?></h1>

<?php if ($images): ?>
	<section class="tile" aria-labelledby="h-fotos">
		<h2 id="h-fotos">Fotos (<?= count($images) ?> de <?= (int) $maxImages ?>)</h2>
		<ul class="thumbs">
			<?php foreach ($images as $n => $img): ?>
				<li>
					<img src="/admin/loja/imagem/<?= (int) $img['id'] ?>" alt="Foto <?= $n + 1 ?> do produto">
					<?php if ($n === 0): ?><span class="badge">Capa</span><?php endif; ?>
					<?php if (count($images) > 1): ?>
						<form method="post" action="/admin/loja/imagem/<?= (int) $img['id'] ?>/excluir">
							<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
							<button class="thumbs__del" type="submit" aria-label="Excluir a foto <?= $n + 1 ?>" title="Excluir foto" data-confirm="Excluir esta foto?">
								<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6"/></svg>
							</button>
						</form>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php if (count($images) === 1): ?><p class="muted">O produto precisa de pelo menos uma foto. Para trocar, envie a nova e depois exclua esta.</p><?php endif; ?>
	</section>
<?php endif; ?>

<form class="tile form" method="post" action="<?= $id ? '/admin/loja/' . (int) $id : '/admin/loja/novo' ?>" enctype="multipart/form-data" novalidate>
	<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
	<?php if ($error): ?><p class="notice notice--err" role="alert"><?= e($error) ?></p><?php endif; ?>
	<div class="row">
		<label class="field">Nome<input type="text" name="name" value="<?= e((string) $old['name']) ?>" maxlength="120"></label>
		<label class="field">Preço (R$)<input type="text" name="price" value="<?= e((string) $old['price']) ?>" inputmode="decimal" placeholder="0,00"></label>
	</div>
	<label class="field">Descrição<textarea name="description" rows="3" maxlength="1000"><?= e((string) $old['description']) ?></textarea></label>
	<label class="field"><?= $images ? 'Adicionar fotos' : 'Fotos (pelo menos uma)' ?> — JPG, PNG ou WebP, até 4 MB cada
		<input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
	</label>
	<label class="check"><input type="checkbox" name="active" value="1"<?= $old['active'] ? ' checked' : '' ?>><span>Visível para os alunos</span></label>

	<h2>Tamanhos e estoque</h2>
	<?php foreach ($variants as $v): $vid = (int) $v['id']; ?>
		<div class="row">
			<label class="field">Tamanho <?= e($v['size']) ?> — em estoque
				<input type="number" name="stock[<?= $vid ?>]" value="<?= (int) $v['stock'] ?>" min="0">
				<input type="hidden" name="stock_before[<?= $vid ?>]" value="<?= (int) $v['stock'] ?>">
			</label>
			<label class="check"><input type="checkbox" name="off[<?= $vid ?>]" value="1"<?= $v['active'] ? '' : ' checked' ?>><span>Retirar o tamanho <?= e($v['size']) ?> da loja</span></label>
		</div>
	<?php endforeach; ?>
	<p class="muted">Novos tamanhos (preencha só o que precisar; use "Único" se não houver tamanho):</p>
	<?php for ($i = 0; $i < $blankRows; $i++): ?>
		<div class="row">
			<label class="field">Novo tamanho<input type="text" name="new[<?= $i ?>][size]" maxlength="20"></label>
			<label class="field">Estoque<input type="number" name="new[<?= $i ?>][stock]" min="0"></label>
		</div>
	<?php endfor; ?>
	<input type="hidden" name="_end" value="1">
	<div class="actions"><button class="btn btn--sm" type="submit">Salvar produto</button></div>
</form>

<?php if ($id): ?>
	<form method="post" action="/admin/loja/<?= (int) $id ?>/excluir">
		<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
		<button class="btn btn--sm btn--danger" type="submit" data-confirm="Excluir este produto? Ele sai da loja. Pedidos já feitos continuam no histórico.">Excluir produto</button>
	</form>
	<script src="<?= e(\Correon\View::asset('/assets/workout-form.js')) ?>" defer></script>
<?php endif; ?>
