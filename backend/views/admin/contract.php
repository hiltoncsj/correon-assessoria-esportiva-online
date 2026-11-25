<?php /** @var array $c @var array $charges @var array $events @var ?array $renewal */
use Correon\Billing;
$d = static fn (?string $v) => $v ? date('d/m/Y', strtotime($v)) : '—';
$active = $c['status'] === 'active';
$canSwap = $active && array_intersect(Billing::modalitiesOf($c['product']), ['corrida', 'triathlon']);
?>
<p class="eyebrow"><a href="/admin/atletas/<?= (int) $c['athlete_id'] ?>">← <?= e($c['athlete']) ?></a></p>
<h1>Contrato #<?= (int) $c['id'] ?></h1>

<section class="tile" aria-labelledby="h-c">
	<h2 id="h-c"><?= e(Billing::PRODUCTS[$c['product']] ?? $c['product']) ?> · <?= e(Billing::PERIODS[$c['period']]) ?></h2>
	<dl class="facts">
		<dt>Situação</dt><dd><?= e(Billing::label($c)) ?></dd>
		<dt>Valor</dt><dd><?= e(Billing::money($c['price_cents'])) ?> em <?= (int) $c['installments'] ?>x</dd>
		<dt>Condição</dt><dd><?= e(Billing::CONDITIONS[$c['price_condition']]) ?></dd>
		<?php if ($c['condition_reason']): ?><dt>Justificativa</dt><dd><?= e($c['condition_reason']) ?></dd><?php endif; ?>
		<dt>Vigência</dt><dd><?= e($d($c['starts_on'])) ?> a <?= e($d($c['ends_on'])) ?></dd>
		<?php if ($c['cancel_reason']): ?><dt>Cancelado</dt><dd><?= e($d($c['cancelled_at'])) ?> — <?= e($c['cancel_reason']) ?></dd><?php endif; ?>
		<?php if ($renewal): ?><dt>Renovação</dt><dd><a href="/admin/contratos/<?= (int) $renewal['id'] ?>">Contrato #<?= (int) $renewal['id'] ?></a></dd><?php endif; ?>
	</dl>
	<?php if ($active): ?>
		<div class="actions">
			<?php if (!$renewal): ?>
				<form method="post" action="/admin/contratos/<?= (int) $c['id'] ?>/renovar">
					<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
					<button class="btn btn--sm" type="submit">Renovar</button>
				</form>
			<?php endif; ?>
			<?php if ($canSwap): ?>
				<form method="post" action="/admin/contratos/<?= (int) $c['id'] ?>/trocar">
					<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
					<button class="btn btn--sm btn--ghost" type="submit" data-confirm="Trocar entre Corrida e Triathlon mantendo o valor?">Trocar Corrida ↔ Triathlon</button>
				</form>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</section>

<h2>Parcelas</h2>
<div class="tbl-wrap">
	<table class="tbl">
		<thead><tr><th>#</th><th>Vencimento</th><th>Valor</th><th>Situação</th><th>Ação</th></tr></thead>
		<tbody>
		<?php foreach ($charges as $ch): $late = $ch['status'] === 'open' && $ch['due_on'] < date('Y-m-d'); ?>
			<tr>
				<td><?= (int) $ch['seq'] ?></td>
				<td><?= e($d($ch['due_on'])) ?></td>
				<td><?= e(Billing::money($ch['amount_cents'])) ?></td>
				<td>
					<?php if ($ch['status'] === 'paid'): ?><span class="badge badge--ok">Paga</span> <span class="muted"><?= e($d($ch['paid_on'])) ?> · <?= e(Billing::METHODS[$ch['method']]) ?></span>
					<?php elseif ($ch['status'] === 'cancelled'): ?><span class="badge">Cancelada</span>
					<?php elseif ($late): ?><span class="badge badge--off">Em atraso</span>
					<?php else: ?><span class="badge badge--draft">Em aberto</span><?php endif; ?>
				</td>
				<td>
					<?php if ($ch['status'] === 'open'): ?>
						<form class="form--inline" method="post" action="/admin/cobrancas/<?= (int) $ch['id'] ?>/pagar">
							<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
							<input type="date" name="paid_on" class="select" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" aria-label="Data do pagamento da parcela <?= (int) $ch['seq'] ?>">
							<select name="method" class="select" aria-label="Forma de pagamento da parcela <?= (int) $ch['seq'] ?>">
								<option value="">Forma…</option>
								<?php foreach (Billing::METHODS as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
							</select>
							<button class="btn btn--sm" type="submit">Dar baixa</button>
						</form>
					<?php elseif ($ch['status'] === 'paid'): ?>
						<form class="form--inline" method="post" action="/admin/cobrancas/<?= (int) $ch['id'] ?>/estornar">
							<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
							<input type="text" name="reason" class="select" maxlength="200" placeholder="motivo" aria-label="Motivo do estorno da parcela <?= (int) $ch['seq'] ?>">
							<button class="btn btn--sm btn--danger" type="submit" data-confirm="Estornar este pagamento?">Estornar</button>
						</form>
					<?php else: ?>—<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>

<?php if ($active): ?>
	<section class="tile" aria-labelledby="h-cancel">
		<h2 id="h-cancel">Cancelar contrato</h2>
		<form class="form" method="post" action="/admin/contratos/<?= (int) $c['id'] ?>/cancelar">
			<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
			<label class="field">Motivo<input type="text" name="reason" maxlength="300"></label>
			<p class="muted">As parcelas em aberto são canceladas; as pagas permanecem. Nenhuma multa é calculada (regra não definida).</p>
			<div class="actions"><button class="btn btn--sm btn--danger" type="submit" data-confirm="Cancelar este contrato?">Cancelar contrato</button></div>
		</form>
	</section>
<?php endif; ?>

<section class="tile" aria-labelledby="h-hist">
	<h2 id="h-hist">Histórico</h2>
	<ul class="plain-list">
		<?php foreach ($events as $ev): ?><li><span class="muted"><?= e(date('d/m/Y H:i', strtotime($ev['created_at']))) ?> · <?= e($ev['who'] ?? 'sistema') ?></span> — <?= e((string) $ev['details']) ?></li><?php endforeach; ?>
	</ul>
</section>
<script src="<?= e(\Correon\View::asset('/assets/workout-form.js')) ?>" defer></script>
