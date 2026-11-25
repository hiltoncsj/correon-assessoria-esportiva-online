<?php /** @var array $rows @var string $filter */
use Correon\Billing;
$filters = ['ativos' => 'Ativos', 'atrasados' => 'Com atraso', 'vencendo' => 'Vencendo / a renovar', 'todos' => 'Todos'];
?>
<p class="eyebrow">Administrador</p>
<h1>Contratos</h1>
<nav class="monthnav" aria-label="Filtro">
	<?php foreach ($filters as $k => $v): ?><a class="btn btn--sm <?= $filter === $k ? '' : 'btn--ghost' ?>" href="/admin/contratos?filtro=<?= e($k) ?>"<?= $filter === $k ? ' aria-current="page"' : '' ?>><?= e($v) ?></a><?php endforeach; ?>
</nav>
<p class="muted">Para criar um contrato, abra o atleta em <a href="/admin/atletas">Atletas</a>.</p>
<?php if (!$rows): ?><div class="tile"><p class="muted">Nenhum contrato neste filtro.</p></div><?php else: ?>
	<div class="tbl-wrap">
		<table class="tbl">
			<thead><tr><th>Contrato</th><th>Atleta</th><th>Plano</th><th>Valor</th><th>Condição</th><th>Vigência</th><th>Situação</th></tr></thead>
			<tbody>
			<?php foreach ($rows as $r): ?>
				<tr>
					<td><a href="/admin/contratos/<?= (int) $r['id'] ?>">#<?= (int) $r['id'] ?></a></td>
					<td><?= e($r['athlete']) ?></td>
					<td><?= e(Billing::PRODUCTS[$r['product']] ?? $r['product']) ?> · <?= e(Billing::PERIODS[$r['period']]) ?></td>
					<td><?= e(Billing::money($r['price_cents'])) ?></td>
					<td><?= e(Billing::CONDITIONS[$r['price_condition']]) ?></td>
					<td><?= e(date('d/m/y', strtotime($r['starts_on']))) ?> – <?= e(date('d/m/y', strtotime($r['ends_on']))) ?></td>
					<td><?= e(Billing::label($r)) ?><?= (int) $r['overdue'] ? ' <span class="badge badge--off">atraso</span>' : '' ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
