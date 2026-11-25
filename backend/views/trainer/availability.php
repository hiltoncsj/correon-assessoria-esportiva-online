<?php /** @var array $slots @var array<string,string> $old @var ?string $error */
use Correon\Templates;
$modes = ['online' => 'Online', 'presencial' => 'Presencial'];
?>
<p class="eyebrow">Treinador</p>
<h1>Disponibilidade</h1>
<p class="muted">Seus horários na semana. Eles serão usados no agendamento do Personal Trainer; por enquanto servem só como registro.</p>

<form class="tile form" method="post" action="/treinador/disponibilidade" novalidate>
	<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
	<?php if ($error): ?><p class="notice notice--err" role="alert"><?= e($error) ?></p><?php endif; ?>
	<div class="row">
		<label class="field">Dia
			<select name="weekday">
				<option value="">—</option>
				<?php foreach (Templates::WEEKDAYS as $n => $label): ?><option value="<?= $n ?>"<?= $old['weekday'] === (string) $n ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
			</select>
		</label>
		<label class="field">Início
			<input type="time" name="start_time" value="<?= e($old['start_time']) ?>">
		</label>
		<label class="field">Fim
			<input type="time" name="end_time" value="<?= e($old['end_time']) ?>">
		</label>
		<label class="field">Atendimento
			<select name="mode">
				<?php foreach ($modes as $k => $label): ?><option value="<?= e($k) ?>"<?= $old['mode'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
			</select>
		</label>
	</div>
	<div class="actions"><button class="btn" type="submit">Adicionar horário</button></div>
</form>

<?php if (!$slots): ?>
	<div class="tile"><p class="muted">Nenhum horário cadastrado.</p></div>
<?php else: ?>
	<div class="tbl-wrap">
		<table class="tbl">
			<thead><tr><th>Dia</th><th>Horário</th><th>Atendimento</th><th></th></tr></thead>
			<tbody>
			<?php foreach ($slots as $s): ?>
				<tr>
					<td><?= e(Templates::WEEKDAYS[(int) $s['weekday']]) ?></td>
					<td><?= e(substr($s['start_time'], 0, 5)) ?> às <?= e(substr($s['end_time'], 0, 5)) ?></td>
					<td><?= e($modes[$s['mode']]) ?></td>
					<td>
						<form method="post" action="/treinador/disponibilidade/<?= (int) $s['id'] ?>/remover">
							<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
							<button class="btn btn--sm btn--danger" type="submit">Remover</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
