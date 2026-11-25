<?php /** @var list<array<string,mixed>> $athletes */ ?>
<p class="eyebrow">Treinador</p>
<h1>Meus atletas</h1>
<p class="muted"><?= (int) $capacity['active'] ?> de <?= (int) $capacity['limit'] ?> vagas ocupadas (online).</p>
<?php if (!$athletes): ?>
	<div class="tile"><p class="muted">Você ainda não tem atletas vinculados. A administração faz a atribuição dos atletas.</p></div>
<?php else: ?>
	<div class="tbl-wrap">
		<table class="tbl">
			<thead><tr><th>Atleta</th><th>Modalidades</th><th>Rascunhos</th><th>Próximos treinos</th><th></th></tr></thead>
			<tbody>
			<?php foreach ($athletes as $a): ?>
				<tr>
					<td><?= e($a['name']) ?></td>
					<td><?= e($a['modalities']) ?></td>
					<td><?= (int) $a['drafts'] ?></td>
					<td><?= (int) $a['upcoming'] ?></td>
					<td><a class="btn btn--sm" href="/treinador/atletas/<?= (int) $a['id'] ?>">Abrir</a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
