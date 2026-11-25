<?php /** @var array $rows @var array<string,string> $old @var ?string $error */ ?>
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
	<p class="muted">Você não define a senha: o sistema gera um link para o treinador criar a dele.</p>
	<div class="actions"><button class="btn btn--sm" type="submit">Cadastrar treinador</button></div>
</form>

<?php if (!$rows): ?>
	<div class="tile"><p class="muted">Nenhum treinador cadastrado.</p></div>
<?php else: ?>
	<div class="tbl-wrap">
		<table class="tbl">
			<thead><tr><th>Treinador</th><th>E-mail</th><th>Atletas</th><th>Conta</th></tr></thead>
			<tbody>
			<?php foreach ($rows as $r): ?>
				<tr>
					<td><a href="/admin/treinadores/<?= (int) $r['id'] ?>"><?= e($r['name']) ?></a></td>
					<td><?= e($r['email']) ?></td>
					<td><?= (int) $r['capacity']['active'] ?> / <?= (int) $r['capacity']['limit'] ?></td>
					<td><?= $r['status'] === 'active' ? '<span class="badge badge--ok">Ativa</span>' : '<span class="badge badge--off">Bloqueada</span>' ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
