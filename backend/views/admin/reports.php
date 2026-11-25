<?php /** @var array $trainers @var array $modalities */ ?>
<p class="eyebrow">Administrador</p>
<h1>Relatórios</h1>

<h2>Por treinador (últimas 4 semanas)</h2>
<?php if (!$trainers): ?><div class="tile"><p class="muted">Nenhum treinador cadastrado.</p></div><?php else: ?>
	<div class="tbl-wrap">
		<table class="tbl">
			<thead><tr><th>Treinador</th><th>Atletas</th><th>Treinos publicados</th><th>Realizados</th><th>Sem registro</th><th>Adesão</th></tr></thead>
			<tbody>
			<?php foreach ($trainers as $t): ?>
				<tr>
					<td><?= e($t['name']) ?></td>
					<td><?= (int) $t['athletes'] ?></td>
					<td><?= (int) $t['planned'] ?></td>
					<td><?= (int) $t['done'] ?></td>
					<td><?= (int) $t['missing'] ?></td>
					<td><?= (int) $t['planned'] > 0 ? (int) round((int) $t['done'] * 100 / (int) $t['planned']) . '%' : '—' ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<p class="muted">Adesão = treinos realizados (inclusive em parte) sobre os publicados até hoje.</p>
<?php endif; ?>

<h2>Por modalidade (vínculos ativos)</h2>
<div class="tbl-wrap">
	<table class="tbl">
		<thead><tr><th>Modalidade</th><th>Atletas</th><th>Treinadores</th></tr></thead>
		<tbody>
		<?php foreach ($modalities as $m): ?><tr><td><?= e($m['name']) ?></td><td><?= (int) $m['athletes'] ?></td><td><?= (int) $m['trainers'] ?></td></tr><?php endforeach; ?>
		</tbody>
	</table>
</div>
