<?php /** @var list<array<string,mixed>> $rows @var list<string> $mods */
$modNames = ['corrida' => 'Corrida', 'triathlon' => 'Triathlon', 'musculacao' => 'Musculação'];
?>
<p class="eyebrow">Treinador</p>
<h1>Modelos de treino</h1>
<p class="muted">Cada modelo (Treino 1, Treino 2…) reúne as <strong>sessões</strong> de um nível de atleta (o dia de cada sessão é escolhido ao enviar). Defina o treino padrão de cada atleta na página dele e depois aplique a semana em “Aplicar modelos”. Cada atleta recebe uma cópia que você pode editar individualmente.</p>

<?php if (!$mods): ?>
	<div class="tile"><p class="muted">Você ainda não tem atletas vinculados, então ainda não há modalidade para criar modelos.</p></div>
<?php else: ?>
	<p class="actions">
		<?php foreach ($mods as $m): ?>
			<a class="btn btn--sm" href="/treinador/modelos/novo?modalidade=<?= e($m) ?>">+ Novo modelo de <?= e($modNames[$m] ?? $m) ?></a>
		<?php endforeach; ?>
		<a class="btn btn--sm btn--ghost" href="/treinador/modelos/aplicar">Aplicar modelos</a>
	</p>
<?php endif; ?>

<?php if (!$rows): ?>
	<div class="tile"><p class="muted">Nenhum modelo criado ainda.</p></div>
<?php else: ?>
	<div class="tbl-wrap">
		<table class="tbl">
			<thead><tr><th>Modelo</th><th>Nível</th><th>Modalidade</th><th>Sessões</th><th>Atletas</th><th>Situação</th><th></th></tr></thead>
			<tbody>
			<?php foreach ($rows as $r): ?>
				<tr>
					<td><?= e($r['name']) ?></td>
					<td><?= e((string) ($r['level_description'] ?? '')) ?: '—' ?></td>
					<td><?= e($r['modality']) ?></td>
					<td><?= (int) $r['session_count'] ?></td>
					<td><?= (int) $r['athletes'] ?></td>
					<td><?= $r['archived_at'] ? '<span class="badge badge--off">Arquivado</span>' : '<span class="badge badge--ok">Ativo</span>' ?></td>
					<td><a class="btn btn--sm btn--ghost" href="/treinador/modelos/<?= (int) $r['id'] ?>">Abrir</a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
