<?php /** @var array $rows @var string $q @var bool $orphan @var int $page @var int $pages @var int $total */
$link = static fn (int $p) => '/admin/atletas?' . http_build_query(array_filter(['q' => $q, 'sem_treinador' => $orphan ? '1' : '', 'pagina' => $p > 1 ? $p : ''], static fn ($v) => $v !== ''));
?>
<p class="eyebrow">Administrador</p>
<h1>Atletas</h1>
<form class="tile form form--inline" method="get" action="/admin/atletas">
	<label class="field">Nome ou e-mail
		<input type="search" name="q" value="<?= e($q) ?>" maxlength="100">
	</label>
	<label class="check"><input type="checkbox" name="sem_treinador" value="1"<?= $orphan ? ' checked' : '' ?>><span>Só sem treinador</span></label>
	<button class="btn btn--sm" type="submit">Buscar</button>
</form>
<p class="muted"><?= (int) $total ?> atleta(s).</p>
<?php if (!$rows): ?>
	<div class="tile"><p class="muted">Nenhum atleta encontrado.</p></div>
<?php else: ?>
	<div class="tbl-wrap">
		<table class="tbl">
			<thead><tr><th>Atleta</th><th>E-mail</th><th>Treinadores</th><th>Cadastro</th><th>Conta</th></tr></thead>
			<tbody>
			<?php foreach ($rows as $r): ?>
				<tr>
					<td><a href="/admin/atletas/<?= (int) $r['id'] ?>"><?= e($r['name']) ?></a></td>
					<td><?= e($r['email']) ?></td>
					<td><?= $r['trainers'] ? e($r['trainers']) : '<span class="badge badge--draft">Sem treinador</span>' ?></td>
					<td><?= e(date('d/m/Y', strtotime($r['created_at']))) ?></td>
					<td><?= $r['status'] === 'active' ? '<span class="badge badge--ok">Ativa</span>' : '<span class="badge badge--off">Bloqueada</span>' ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
<?php if ($pages > 1): ?>
	<nav class="monthnav" aria-label="Páginas">
		<?php if ($page > 1): ?><a class="btn btn--sm btn--ghost" href="<?= e($link($page - 1)) ?>">← Anterior</a><?php endif; ?>
		<span class="muted">Página <?= (int) $page ?> de <?= (int) $pages ?></span>
		<?php if ($page < $pages): ?><a class="btn btn--sm btn--ghost" href="<?= e($link($page + 1)) ?>">Próxima →</a><?php endif; ?>
	</nav>
<?php endif; ?>
