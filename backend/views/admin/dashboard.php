<?php /** @var array $counts @var array $numbers @var array $orphans @var array $trainers */ ?>
<p class="eyebrow">Administrador</p>
<h1>Painel administrativo</h1>

<div class="grid">
	<section class="tile" aria-labelledby="t-users">
		<h2 id="t-users">Usuários por perfil</h2>
		<ul class="plain-list">
			<?php foreach ($counts as $c): ?><li><?= e($c['name']) ?>: <strong><?= (int) $c['total'] ?></strong></li><?php endforeach; ?>
		</ul>
		<p class="muted"><?= (int) $numbers['blocked'] ?> conta(s) bloqueada(s).</p>
	</section>
	<section class="tile" aria-labelledby="t-new">
		<h2 id="t-new">Novos atletas (30 dias)</h2>
		<p class="stat"><?= (int) $numbers['new_athletes'] ?></p>
	</section>
	<section class="tile" aria-labelledby="t-week">
		<h2 id="t-week">Treinos</h2>
		<p class="stat"><?= (int) $numbers['week_workouts'] ?><small> publicados nesta semana</small></p>
		<p class="muted"><?= (int) $numbers['week_logs'] ?> registro(s) de atletas nos últimos 7 dias.</p>
	</section>
</div>

<section class="tile" aria-labelledby="t-orf">
	<h2 id="t-orf">Atletas sem treinador (<?= count($orphans) ?>)</h2>
	<?php if (!$orphans): ?>
		<p class="muted">Todos os atletas ativos têm treinador.</p>
	<?php else: ?>
		<ul class="plain-list">
			<?php foreach ($orphans as $a): ?><li><a href="/admin/atletas/<?= (int) $a['id'] ?>"><?= e($a['name']) ?></a></li><?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>

<h2>Capacidade dos treinadores</h2>
<?php if (!$trainers): ?>
	<div class="tile"><p class="muted">Nenhum treinador cadastrado. <a href="/admin/treinadores">Cadastrar</a></p></div>
<?php else: ?>
	<div class="tbl-wrap">
		<table class="tbl">
			<thead><tr><th>Treinador</th><th>Atletas</th><th>Uso</th><th>Situação</th></tr></thead>
			<tbody>
			<?php foreach ($trainers as $t): $c = $t['capacity']; ?>
				<tr>
					<td><a href="/admin/treinadores/<?= (int) $t['id'] ?>"><?= e($t['name']) ?></a></td>
					<td><?= (int) $c['active'] ?> / <?= (int) $c['limit'] ?></td>
					<td><?= (int) $c['pct'] ?>%</td>
					<td>
						<?php if ($t['status'] !== 'active'): ?><span class="badge badge--off">Bloqueado</span>
						<?php elseif ($c['state'] === 'full'): ?><span class="badge badge--off">Esgotado</span>
						<?php elseif ($c['state'] === 'alert'): ?><span class="badge badge--draft">Atenção</span>
						<?php else: ?><span class="badge badge--ok">Disponível</span><?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
<p class="muted">Planos, contratos, financeiro, Personal Trainer e conteúdo entram nas próximas etapas.</p>
