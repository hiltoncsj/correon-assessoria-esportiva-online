<?php /** @var array $person @var array<string,string> $old @var ?string $error @var array $table */
use Correon\Billing;
$opts = static function (array $list, string $sel): string {
	$h = '';
	foreach ($list as $k => $v) { $h .= '<option value="' . e((string) $k) . '"' . ((string) $k === $sel ? ' selected' : '') . '>' . e($v) . '</option>'; }
	return $h;
};
?>
<p class="eyebrow"><a href="/admin/atletas/<?= (int) $person['id'] ?>">← <?= e($person['name']) ?></a></p>
<h1>Novo contrato</h1>
<form class="tile form" method="post" action="/admin/atletas/<?= (int) $person['id'] ?>/contrato" novalidate>
	<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
	<?php if ($error): ?><p class="notice notice--err" role="alert"><?= e($error) ?></p><?php endif; ?>
	<div class="row">
		<label class="field">Produto<select name="product"><option value="">—</option><?= $opts(Billing::PRODUCTS, $old['product']) ?></select></label>
		<label class="field">Período<select name="period"><?= $opts(Billing::PERIODS, $old['period']) ?></select></label>
		<label class="field">Condição<select name="condition"><?= $opts(Billing::CONDITIONS, $old['condition']) ?></select></label>
	</div>
	<div class="row">
		<label class="field">Valor total (só "valor negociado")<input type="text" inputmode="decimal" name="value" value="<?= e($old['value']) ?>" maxlength="10" placeholder="ex.: 150,00"></label>
		<label class="field">Parcelas<input type="text" inputmode="numeric" name="installments" value="<?= e($old['installments']) ?>" maxlength="2"></label>
	</div>
	<label class="field">Justificativa (obrigatória no valor negociado)<input type="text" name="reason" value="<?= e($old['reason']) ?>" maxlength="300"></label>
	<div class="row">
		<label class="field">Início<input type="date" name="starts_on" value="<?= e($old['starts_on']) ?>"></label>
		<label class="field">1º vencimento (vazio = início)<input type="date" name="first_due" value="<?= e($old['first_due']) ?>"></label>
	</div>
	<p class="muted">"Preço de tabela" usa o valor da tela Preços. "Aluno antigo" aplica R$ 120 no mensal de uma modalidade; só use para quem já era aluno.</p>
	<div class="actions"><button class="btn" type="submit">Criar contrato</button></div>
</form>

<h2>Tabela atual</h2>
<div class="tbl-wrap">
	<table class="tbl">
		<thead><tr><th>Produto</th><?php foreach (Billing::PERIODS as $label): ?><th><?= e($label) ?></th><?php endforeach; ?></tr></thead>
		<tbody>
		<?php foreach (Billing::PRODUCTS as $product => $label): ?>
			<tr><td><?= e($label) ?></td>
				<?php foreach (Billing::PERIODS as $period => $pl): $row = $table[$product][$period] ?? ['total' => null, 'max' => 1]; ?>
					<td><?= e(Billing::money($row['total'])) ?><?= $row['total'] !== null && $row['max'] > 1 ? '<br><span class="muted">até ' . (int) $row['max'] . 'x</span>' : '' ?></td>
				<?php endforeach; ?>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
