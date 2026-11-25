<?php /** @var array $rows @var string $type */
use Correon\Content;
?>
<p class="eyebrow">Administrador</p>
<h1>Conteúdo</h1>
<p class="muted">O que for publicado aqui aparece na seção “CorreON em movimento” do site, sem precisar publicar o site de novo.</p>
<p class="actions">
	<?php foreach (Content::TYPES as $k => $v): ?><a class="btn btn--sm" href="/admin/conteudo/novo?tipo=<?= e($k) ?>">+ <?= e($v) ?></a><?php endforeach; ?>
</p>
<nav class="monthnav" aria-label="Filtro">
	<a class="btn btn--sm <?= $type === '' ? '' : 'btn--ghost' ?>" href="/admin/conteudo">Todos</a>
	<?php foreach (Content::TYPES as $k => $v): ?><a class="btn btn--sm <?= $type === $k ? '' : 'btn--ghost' ?>" href="/admin/conteudo?tipo=<?= e($k) ?>"><?= e($v) ?>s</a><?php endforeach; ?>
</nav>
<?php if (!$rows): ?><div class="tile"><p class="muted">Nada cadastrado ainda. O site mostra “Em breve”.</p></div><?php else: ?>
	<div class="tbl-wrap">
		<table class="tbl">
			<thead><tr><th>Título</th><th>Tipo</th><th>Data</th><th>Situação</th></tr></thead>
			<tbody>
			<?php foreach ($rows as $r): $scheduled = $r['status'] === 'published' && $r['publish_at'] && $r['publish_at'] > date('Y-m-d H:i:s'); ?>
				<tr>
					<td><a href="/admin/conteudo/<?= (int) $r['id'] ?>"><?= e($r['title']) ?></a></td>
					<td><?= e(Content::TYPES[$r['type']]) ?></td>
					<td><?= e(date('d/m/Y', strtotime($r['content_date']))) ?></td>
					<td>
						<span class="badge <?= $r['status'] === 'published' ? 'badge--ok' : ($r['status'] === 'archived' ? 'badge--off' : 'badge--draft') ?>"><?= $scheduled ? 'Agendado' : e(Content::STATUS[$r['status']]) ?></span>
						<?php if ($r['status'] === 'published' && $r['athlete_id'] && !Content::hasConsent((int) $r['athlete_id'])): ?><span class="badge badge--off">fora do ar: sem autorização</span><?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
