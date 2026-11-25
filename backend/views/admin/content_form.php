<?php /** @var string $type @var ?array $c @var array<string,string> $vals @var list<string> $errors @var array $athletes */
use Correon\Content;
$action = $c ? '/admin/conteudo/' . (int) $c['id'] : '/admin/conteudo';
$v = static fn (string $k) => e($vals[$k] ?? '');
$field = static function (string $label, string $name, int $max, string $t = 'text', string $ph = '') use ($v) {
	return '<label class="field">' . e($label) . '<input type="' . $t . '" name="' . $name . '" value="' . $v($name) . '" maxlength="' . $max . '"' . ($ph ? ' placeholder="' . e($ph) . '"' : '') . '></label>';
};
?>
<p class="eyebrow"><a href="/admin/conteudo">← Conteúdo</a></p>
<h1><?= $c ? 'Editar ' : 'Novo: ' ?><?= e(Content::TYPES[$type]) ?></h1>
<?php if ($c): ?><p><span class="badge <?= $c['status'] === 'published' ? 'badge--ok' : 'badge--draft' ?>"><?= e(Content::STATUS[$c['status']]) ?></span></p><?php endif; ?>
<?php if ($errors): ?><div class="notice notice--err" role="alert"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

<form class="tile form" method="post" action="<?= e($action) ?>" enctype="multipart/form-data" novalidate>
	<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
	<?php if (!$c): ?><input type="hidden" name="type" value="<?= e($type) ?>"><?php endif; ?>

	<div class="row">
		<?= $field($type === 'result' ? 'Nome do atleta (como aparece no site)' : ($type === 'news' ? 'Título' : 'Nome do evento'), 'title', 160) ?>
		<?= $field($type === 'news' ? 'Data de publicação' : 'Data', 'content_date', 10, 'date') ?>
		<?= $field($type === 'news' ? 'Categoria' : 'Modalidade', 'category', 60, 'text', $type === 'news' ? '' : 'Corrida, Triathlon…') ?>
	</div>

	<?php if ($type === 'result'): ?>
		<div class="row">
			<?= $field('Competição', 'competition', 160) ?>
			<?= $field('Distância ou categoria', 'distance', 60) ?>
			<?= $field('Resultado ou tempo', 'result_text', 60) ?>
			<?= $field('Colocação (opcional)', 'placement', 60) ?>
		</div>
	<?php endif; ?>
	<?php if ($type === 'event'): ?>
		<div class="row">
			<?= $field('Local', 'place', 160) ?>
			<?= $field('Horário (opcional)', 'event_time', 20, 'text', '07h00') ?>
			<label class="field">Situação
				<select name="event_status"><?php foreach (Content::EVENT_STATUS as $k => $label): ?><option value="<?= e($k) ?>"<?= ($vals['event_status'] ?? '') === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
			</label>
		</div>
		<?= $field('Link de inscrição (opcional)', 'registration_url', 300, 'url', 'https://') ?>
	<?php endif; ?>

	<label class="field"><?= $type === 'news' ? 'Resumo' : 'Texto (opcional)' ?>
		<textarea name="summary" rows="4" maxlength="600"><?= $v('summary') ?></textarea>
	</label>
	<?= $field($type === 'news' ? 'Link para a notícia completa (opcional)' : 'Link de detalhes (opcional)', 'link_url', 300, 'url', 'https://') ?>

	<h2>Foto</h2>
	<?php if ($c && $c['image_id']): ?>
		<img class="preview" src="/admin/imagem/<?= (int) $c['image_id'] ?>" alt="Foto atual">
		<label class="check"><input type="checkbox" name="remove_image" value="1"><span>Remover esta foto</span></label>
	<?php endif; ?>
	<label class="field">Enviar foto (JPG, PNG ou WebP, até 4 MB)<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
	<?= $field('Descrição da foto (para quem não enxerga)', 'image_alt', 200) ?>

	<h2>Autorização e publicação</h2>
	<label class="field">Atleta cadastrado citado (opcional)
		<select name="athlete_id">
			<option value="">— nenhum —</option>
			<?php foreach ($athletes as $a): ?><option value="<?= (int) $a['id'] ?>"<?= ($vals['athlete_id'] ?? '') === (string) $a['id'] ? ' selected' : '' ?>><?= e($a['name']) ?><?= (int) $a['ok'] ? ' ✓ autorizou' : ' — sem autorização' ?></option><?php endforeach; ?>
		</select>
	</label>
	<label class="check"><input type="checkbox" name="consent_declared" value="1"<?= ($vals['consent_declared'] ?? '') === '1' ? ' checked' : '' ?>><span>Tenho autorização para publicar estas informações e a imagem das pessoas que aparecem (inclusive responsáveis, se houver menores).</span></label>
	<label class="field">Agendar publicação (opcional)<input type="datetime-local" name="publish_at" value="<?= $v('publish_at') ?>"></label>

	<div class="actions">
		<?php if (!$c || $c['status'] !== 'published'): ?>
			<button class="btn btn--ghost" type="submit" name="action" value="save">Salvar rascunho</button>
			<button class="btn" type="submit" name="action" value="publish">Publicar</button>
		<?php else: ?>
			<button class="btn" type="submit" name="action" value="publish">Salvar alterações</button>
			<button class="btn btn--ghost" type="submit" name="action" value="unpublish">Tirar do ar</button>
		<?php endif; ?>
		<?php if ($c && $c['status'] !== 'archived'): ?><button class="btn btn--danger" type="submit" name="action" value="archive">Arquivar</button><?php endif; ?>
	</div>
	<input type="hidden" name="_end" value="1">
</form>
