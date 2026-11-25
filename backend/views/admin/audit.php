<?php /** @var array $rows @var array $actions @var string $action @var string $day @var int $page @var int $pages @var int $total */
$link = static fn (int $p) => '/admin/auditoria?' . http_build_query(array_filter(['acao' => $action, 'dia' => $day, 'pagina' => $p > 1 ? $p : ''], static fn ($v) => $v !== ''));
?>
<p class="eyebrow">Administrador</p>
<h1>Auditoria</h1>
<form class="tile form form--inline" method="get" action="/admin/auditoria">
	<label class="field">Ação
		<select name="acao">
			<option value="">Todas</option>
			<?php foreach ($actions as $a): ?><option value="<?= e($a) ?>"<?= $a === $action ? ' selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
		</select>
	</label>
	<label class="field">Dia<input type="date" name="dia" value="<?= e($day) ?>"></label>
	<button class="btn btn--sm" type="submit">Filtrar</button>
</form>
<p class="muted"><?= (int) $total ?> registro(s).</p>
<div class="tbl-wrap">
	<table class="tbl">
		<thead><tr><th>Quando</th><th>Quem</th><th>Ação</th><th>Sobre</th><th>Detalhes</th><th>IP</th></tr></thead>
		<tbody>
		<?php foreach ($rows as $r): ?>
			<tr>
				<td><?= e(date('d/m/Y H:i:s', strtotime($r['created_at']))) ?></td>
				<td><?= $r['who'] ? e($r['who']) : '<span class="muted">sistema</span>' ?></td>
				<td><?= e($r['action']) ?></td>
				<td><?= $r['entity_type'] ? e($r['entity_type'] . ($r['entity_id'] ? ' #' . $r['entity_id'] : '')) : '—' ?></td>
				<td><?= e((string) ($r['details'] ?? '')) ?: '—' ?></td>
				<td><?= e((string) $r['ip']) ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php if ($pages > 1): ?>
	<nav class="monthnav" aria-label="Páginas">
		<?php if ($page > 1): ?><a class="btn btn--sm btn--ghost" href="<?= e($link($page - 1)) ?>">← Mais recentes</a><?php endif; ?>
		<span class="muted">Página <?= (int) $page ?> de <?= (int) $pages ?></span>
		<?php if ($page < $pages): ?><a class="btn btn--sm btn--ghost" href="<?= e($link($page + 1)) ?>">Mais antigos →</a><?php endif; ?>
	</nav>
<?php endif; ?>
