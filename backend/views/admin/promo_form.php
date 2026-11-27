<?php /** @var ?int $id @var array<string,string> $old @var ?string $error @var ?string $mime */ ?>
<p class="eyebrow"><a href="/admin/promocao">← Promoção</a></p>
<h1><?= $id ? 'Editar promoção' : 'Nova promoção' ?></h1>
<form class="tile form" method="post" action="<?= $id ? '/admin/promocao/' . (int) $id : '/admin/promocao/novo' ?>" enctype="multipart/form-data" novalidate>
	<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
	<?php if ($error): ?><p class="notice notice--err" role="alert"><?= e($error) ?></p><?php endif; ?>
	<label class="field">Título (aparece na faixa do cronômetro)<input type="text" name="title" value="<?= e($old['title']) ?>" maxlength="120"></label>
	<label class="field">Texto curto (opcional)<input type="text" name="body" value="<?= e($old['body']) ?>" maxlength="300"></label>
	<div class="row">
		<label class="field">Início — data<input type="date" name="start_date" value="<?= e($old['start_date']) ?>"></label>
		<label class="field">Início — hora<input type="time" name="start_time" value="<?= e($old['start_time']) ?>"></label>
	</div>
	<div class="row">
		<label class="field">Fim — data<input type="date" name="end_date" value="<?= e($old['end_date']) ?>"></label>
		<label class="field">Fim — hora<input type="time" name="end_time" value="<?= e($old['end_time']) ?>"></label>
	</div>

	<?php if ($mime !== null): ?>
		<?php if (str_starts_with($mime, 'video/')): ?>
			<video class="preview" src="/admin/promocao/<?= (int) $id ?>/midia" controls muted playsinline preload="metadata"></video>
		<?php else: ?>
			<img class="preview" src="/admin/promocao/<?= (int) $id ?>/midia" alt="Imagem atual da promoção">
		<?php endif; ?>
	<?php endif; ?>
	<label class="field"><?= $mime !== null ? 'Trocar a imagem ou o vídeo' : 'Imagem ou vídeo' ?> — imagem JPG, PNG ou WebP (até 4 MB) ou vídeo MP4/WebM (até 30 MB)
		<input type="file" name="media" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm">
	</label>
	<p class="muted">Medida ideal: <strong>1920 × 1080 px</strong> (horizontal, 16:9). Ela aparece inteira, abaixo do cronômetro, sem cortar nem esticar. No computador fica à esquerda, com o texto e o botão à direita; no celular em pé, o texto e o botão ficam abaixo.</p>

	<div class="row">
		<label class="field">Texto do botão (opcional)<input type="text" name="button_label" value="<?= e($old['button_label']) ?>" maxlength="40"></label>
		<label class="field">Link do botão<input type="text" name="link_url" value="<?= e($old['link_url']) ?>" maxlength="500" placeholder="#planos, /cadastro ou https://..."></label>
	</div>
	<label class="field">Desconto no plano anual, em % (opcional)
		<input type="text" inputmode="decimal" name="annual_discount_pct" value="<?= e($old['annual_discount_pct']) ?>" maxlength="5" placeholder="ex.: 10">
	</label>
	<p class="muted">Durante a promoção, vale para todos os preços anuais (somado ao desconto do combo), no site e nos novos contratos a preço de tabela. A renovação desses contratos volta ao preço de tabela.</p>
	<label class="check"><input type="checkbox" name="enabled" value="1"<?= $old['enabled'] === '1' ? ' checked' : '' ?>><span>Ativa (aparece no período informado)</span></label>
	<input type="hidden" name="_end" value="1">
	<div class="actions"><button class="btn btn--sm" type="submit">Salvar promoção</button></div>
</form>

<?php if ($id): ?>
	<form method="post" action="/admin/promocao/<?= (int) $id ?>/excluir">
		<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
		<button class="btn btn--sm btn--danger" type="submit" data-confirm="Excluir esta promoção e o arquivo enviado?">Excluir promoção</button>
	</form>
	<script src="<?= e(\Correon\View::asset('/assets/workout-form.js')) ?>" defer></script>
<?php endif; ?>
