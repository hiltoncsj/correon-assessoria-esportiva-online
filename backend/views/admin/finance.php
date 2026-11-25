<?php /** @var array $totals @var array $overdue @var string $month */
use Correon\Billing;
?>
<p class="eyebrow">Administrador</p>
<h1>Financeiro</h1>
<form class="tile form form--inline" method="get" action="/admin/financeiro">
	<label class="field">Mês<input type="month" name="mes" value="<?= e($month) ?>"></label>
	<button class="btn btn--sm" type="submit">Ver</button>
</form>
<div class="grid">
	<section class="tile"><h2>Recebido no mês</h2><p class="stat"><?= e(Billing::money($totals['received'])) ?></p></section>
	<section class="tile"><h2>Com vencimento no mês</h2><p class="stat"><?= e(Billing::money($totals['due'])) ?></p></section>
	<section class="tile"><h2>Em atraso (total)</h2><p class="stat"><?= e(Billing::money($totals['overdue'])) ?></p></section>
	<section class="tile"><h2>Atletas com contrato vigente</h2><p class="stat"><?= (int) $totals['athletes'] ?></p></section>
</div>
<h2>Parcelas em atraso</h2>
<?php if (!$overdue): ?><div class="tile"><p class="muted">Nenhuma parcela em atraso.</p></div><?php else: ?>
	<div class="tbl-wrap">
		<table class="tbl">
			<thead><tr><th>Atleta</th><th>Contrato</th><th>Vencimento</th><th>Dias</th><th>Valor</th></tr></thead>
			<tbody>
			<?php foreach ($overdue as $o): ?>
				<tr><td><?= e($o['athlete']) ?></td><td><a href="/admin/contratos/<?= (int) $o['contract_id'] ?>">#<?= (int) $o['contract_id'] ?> (parcela <?= (int) $o['seq'] ?>)</a></td>
					<td><?= e(date('d/m/Y', strtotime($o['due_on']))) ?></td><td><?= (int) $o['days'] ?></td><td><?= e(Billing::money($o['amount_cents'])) ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
<p class="muted">Não há suspensão automática de acesso: o prazo de tolerância ainda não foi definido.</p>
