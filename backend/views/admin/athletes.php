<?php /** @var array $rows @var string $q @var bool $orphan @var int $page @var int $pages @var int $total @var array<string,string> $old @var ?string $error @var array<string,string> $modalities @var array $trainers */
$link = static fn (int $p) => '/admin/atletas?' . http_build_query(array_filter(['q' => $q, 'sem_treinador' => $orphan ? '1' : '', 'pagina' => $p > 1 ? $p : ''], static fn ($v) => $v !== ''));
?>
<p class="eyebrow">Administrador</p>
<h1>Atletas</h1>

<details class="tile newbox"<?= $error ? ' open' : '' ?>>
	<summary>+ Novo atleta</summary>
	<form class="form" method="post" action="/admin/atletas" novalidate>
		<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
		<?php if ($error): ?><p class="notice notice--err" role="alert"><?= e($error) ?></p><?php endif; ?>
		<div class="row">
			<label class="field">Nome completo<input type="text" name="name" value="<?= e($old['name']) ?>" maxlength="120"></label>
			<label class="field">E-mail<input type="email" name="email" value="<?= e($old['email']) ?>" maxlength="190"></label>
		</div>
		<label class="field">Senha provisória (mínimo 10 caracteres)<input type="text" name="temp_password" maxlength="72" autocomplete="off" spellcheck="false"></label>
		<div class="row">
			<label class="field">Modalidade (opcional)
				<select name="modality" data-filter-source>
					<option value="">—</option>
					<?php foreach ($modalities as $slug => $label): ?><option value="<?= e($slug) ?>"<?= $old['modality'] === $slug ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
				</select>
			</label>
			<label class="field">Treinador (opcional)
				<select name="trainer_id" data-filter-target>
					<option value="">—</option>
					<?php foreach ($trainers as $t): ?>
						<option value="<?= (int) $t['id'] ?>" data-mods="<?= e(implode(' ', $t['modalities'])) ?>"<?= $old['trainer_id'] === (string) $t['id'] ? ' selected' : '' ?>><?= e($t['name']) ?> (<?= (int) $t['capacity']['active'] ?>/<?= (int) $t['capacity']['limit'] ?>)</option>
					<?php endforeach; ?>
				</select>
			</label>
		</div>
		<p class="muted">O atleta é obrigado a trocar a senha e a aceitar os Termos no primeiro acesso. Use uma senha diferente para cada pessoa. O contrato é feito na página do atleta.</p>
		<div class="actions"><button class="btn btn--sm" type="submit">Cadastrar atleta</button></div>
	</form>
</details>
<script src="<?= e(\Correon\View::asset('/assets/workout-form.js')) ?>" defer></script>

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
