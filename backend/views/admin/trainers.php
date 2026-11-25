<?php /** @var array $rows @var array<string,string> $old @var ?string $error @var array<string,string> $modalities @var list<string> $checked */ ?>
<p class="eyebrow">Administrador</p>
<h1>Treinadores</h1>

<form class="tile form" method="post" action="/admin/treinadores" novalidate>
	<h2>Novo treinador</h2>
	<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
	<?php if ($error): ?><p class="notice notice--err" role="alert"><?= e($error) ?></p><?php endif; ?>
	<div class="row">
		<label class="field">Nome completo<input type="text" name="name" value="<?= e($old['name']) ?>" maxlength="120"></label>
		<label class="field">E-mail<input type="email" name="email" value="<?= e($old['email']) ?>" maxlength="190"></label>
	</div>
	<fieldset class="days">
		<legend>Modalidades que atende</legend>
		<?php foreach ($modalities as $slug => $label): ?>
			<label class="days__d"><input type="checkbox" name="modalities[]" value="<?= e($slug) ?>"<?= in_array($slug, $checked, true) ? ' checked' : '' ?>> <?= e($label) ?></label>
		<?php endforeach; ?>
	</fieldset>
	<label class="field">Senha provisória (mínimo 10 caracteres)
		<input type="text" name="temp_password" maxlength="72" autocomplete="off" spellcheck="false">
	</label>
	<p class="muted">O treinador entra com ela e é obrigado a trocar no primeiro acesso. Use uma senha diferente para cada pessoa. Se deixar em branco, o sistema gera um link para ele criar a própria senha.</p>
	<div class="actions"><button class="btn btn--sm" type="submit">Cadastrar treinador</button></div>
</form>

<?php if (!$rows): ?>
	<div class="tile"><p class="muted">Nenhum treinador cadastrado.</p></div>
<?php else: ?>
	<div class="tbl-wrap">
		<table class="tbl">
			<thead><tr><th>Treinador</th><th>E-mail</th><th>Modalidades</th><th>Atletas</th><th>Conta</th></tr></thead>
			<tbody>
			<?php foreach ($rows as $r): ?>
				<tr>
					<td><a href="/admin/treinadores/<?= (int) $r['id'] ?>"><?= e($r['name']) ?></a></td>
					<td><?= e($r['email']) ?></td>
					<td><?= $r['modalities'] ? e(implode(', ', array_map(static fn ($s) => $modalities[$s], $r['modalities']))) : '<span class="badge badge--draft">definir</span>' ?></td>
					<td><?= (int) $r['capacity']['active'] ?> / <?= (int) $r['capacity']['limit'] ?></td>
					<td><?= $r['status'] === 'active' ? '<span class="badge badge--ok">Ativa</span>' : '<span class="badge badge--off">Bloqueada</span>' ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
