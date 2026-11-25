<?php /** @var array $contracts @var array<int,array> $charges */
use Correon\Billing;
$d = static fn (?string $v) => $v ? date('d/m/Y', strtotime($v)) : '—';
?>
<p class="eyebrow">Atleta</p>
<h1>Assinaturas e pagamentos</h1>
<?php if (!$contracts): ?>
	<div class="tile"><p class="muted">Você ainda não tem contrato registrado. A contratação é feita com a CorreON.</p></div>
<?php endif; ?>
<?php foreach ($contracts as $c): ?>
	<section class="tile" aria-label="Contrato <?= (int) $c['id'] ?>">
		<h2><?= e(Billing::PRODUCTS[$c['product']] ?? $c['product']) ?> · <?= e(Billing::PERIODS[$c['period']]) ?></h2>
		<dl class="facts">
			<dt>Situação</dt><dd><?= e(Billing::label($c)) ?></dd>
			<dt>Valor</dt><dd><?= e(Billing::money($c['price_cents'])) ?> em <?= (int) $c['installments'] ?>x</dd>
			<dt>Vigência</dt><dd><?= e($d($c['starts_on'])) ?> a <?= e($d($c['ends_on'])) ?></dd>
		</dl>
		<div class="tbl-wrap">
			<table class="tbl">
				<thead><tr><th>Parcela</th><th>Vencimento</th><th>Valor</th><th>Situação</th></tr></thead>
				<tbody>
				<?php foreach ($charges[(int) $c['id']] ?? [] as $ch): ?>
					<tr>
						<td><?= (int) $ch['seq'] ?></td>
						<td><?= e($d($ch['due_on'])) ?></td>
						<td><?= e(Billing::money($ch['amount_cents'])) ?></td>
						<td><?= $ch['status'] === 'paid' ? '<span class="badge badge--ok">Paga</span>' : ($ch['status'] === 'cancelled' ? '<span class="badge">Cancelada</span>' : ($ch['due_on'] < date('Y-m-d') ? '<span class="badge badge--off">Em atraso</span>' : '<span class="badge badge--draft">Em aberto</span>')) ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>
<?php endforeach; ?>
<?php if ($contracts): ?><p class="muted">O pagamento é combinado com a CorreON; a baixa é registrada pela administração.</p><?php endif; ?>
