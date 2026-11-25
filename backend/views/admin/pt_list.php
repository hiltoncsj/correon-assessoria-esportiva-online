<?php /** @var array $rows */
use Correon\{Billing, Personal};
?>
<p class="eyebrow">Administrador</p>
<h1>Personal Trainer</h1>
<p class="muted">Para cadastrar, abra o atleta em <a href="/admin/atletas">Atletas</a> e clique em “Novo Personal”.</p>
<?php if (!$rows): ?><div class="tile"><p class="muted">Nenhum Personal cadastrado.</p></div><?php else: ?>
	<div class="tbl-wrap">
		<table class="tbl">
			<thead><tr><th>#</th><th>Atleta</th><th>Treinador</th><th>Formato</th><th>Saldo</th><th>Valor</th><th>Situação</th></tr></thead>
			<tbody>
			<?php foreach ($rows as $r): $b = $r['balance']; ?>
				<tr>
					<td><a href="/admin/personal/<?= (int) $r['id'] ?>">#<?= (int) $r['id'] ?></a></td>
					<td><?= e($r['athlete']) ?></td>
					<td><?= e($r['trainer']) ?></td>
					<td><?= e(Personal::FORMATS[$r['format']]) ?></td>
					<td><?= $b['remaining'] === null ? (int) $r['weekly_frequency'] . '/semana · ' . (int) $b['used'] . ' feitas' : (int) $b['remaining'] . ' de ' . (int) $r['sessions_total'] ?></td>
					<td><?= e(Billing::money($r['price_cents'])) ?><?= $r['price_cents'] !== null && !$r['paid_on'] ? ' <span class="badge badge--draft">em aberto</span>' : '' ?></td>
					<td><?= $r['status'] === 'cancelled' ? 'Cancelado' : ($r['valid_until'] && $r['valid_until'] < date('Y-m-d') ? 'Vencido' : 'Ativo') ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
