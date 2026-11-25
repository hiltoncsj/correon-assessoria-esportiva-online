<?php /** @var array $table */
use Correon\Billing;
?>
<p class="eyebrow">Administrador</p>
<h1>Preços</h1>
<p class="muted">Valor <strong>total</strong> de cada período e máximo de parcelas. Campo vazio = não vendido. Mudar aqui vale só para contratos <strong>novos</strong>. A página pública (seção de planos) tem os preços dela e precisa ser atualizada à parte.</p>
<form class="form form--wide" method="post" action="/admin/precos" novalidate>
	<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
	<div class="tbl-wrap">
		<table class="tbl ztbl">
			<thead><tr><th>Produto</th><?php foreach (Billing::PERIODS as $label): ?><th><?= e($label) ?></th><?php endforeach; ?></tr></thead>
			<tbody>
			<?php foreach (Billing::PRODUCTS as $product => $label): ?>
				<tr>
					<td><?= e($label) ?></td>
					<?php foreach (Billing::PERIODS as $period => $pl): $row = $table[$product][$period] ?? ['total' => null, 'max' => 1]; ?>
						<td>
							<input type="text" inputmode="decimal" name="price[<?= e($product) ?>][<?= e($period) ?>]" value="<?= $row['total'] === null ? '' : e(number_format($row['total'] / 100, 2, ',', '.')) ?>" maxlength="10" aria-label="<?= e("$label $pl: valor total") ?>" placeholder="R$">
							<input type="text" inputmode="numeric" name="max[<?= e($product) ?>][<?= e($period) ?>]" value="<?= (int) $row['max'] ?>" maxlength="2" aria-label="<?= e("$label $pl: máximo de parcelas") ?>" title="máximo de parcelas">
						</td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<p class="muted">Em cada célula: valor total (em cima) e máximo de parcelas (embaixo).</p>
	<div class="actions"><button class="btn" type="submit">Salvar preços</button></div>
</form>
