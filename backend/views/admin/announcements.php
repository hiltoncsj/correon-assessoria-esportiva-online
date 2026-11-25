<?php /** @var array $sent */ ?>
<p class="eyebrow">Administrador</p>
<h1>Comunicados</h1>
<form class="tile form" method="post" action="/admin/comunicados" novalidate>
	<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
	<label class="field">Título<input type="text" name="title" maxlength="120"></label>
	<label class="field">Mensagem (opcional)<textarea name="body" rows="4" maxlength="600"></textarea></label>
	<label class="field">Para quem
		<select name="audience"><option value="all">Todos os atletas</option><option value="corrida">Atletas de Corrida</option><option value="triathlon">Atletas de Triathlon</option><option value="musculacao">Atletas de Musculação</option></select>
	</label>
	<p class="muted">O aviso aparece na área do atleta. Não é enviado por e-mail nem WhatsApp.</p>
	<div class="actions"><button class="btn" type="submit">Enviar aviso</button></div>
</form>
<h2>Enviados</h2>
<?php if (!$sent): ?><div class="tile"><p class="muted">Nenhum comunicado enviado.</p></div><?php else: ?>
	<ul class="plain-list tile">
		<?php foreach ($sent as $s): $d = json_decode((string) $s['details'], true) ?: []; ?>
			<li><?= e(date('d/m/Y H:i', strtotime($s['created_at']))) ?> — <strong><?= e((string) ($d['title'] ?? '')) ?></strong> <span class="muted"><?= (int) ($d['recipients'] ?? 0) ?> atleta(s) · por <?= e((string) $s['who']) ?></span></li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
