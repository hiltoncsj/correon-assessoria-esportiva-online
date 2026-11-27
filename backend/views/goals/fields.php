<?php /** @var array<string,string> $old */
use Correon\Goals;
?>
<div class="row">
	<label class="field">Tipo
		<select name="kind">
			<?php foreach (Goals::KINDS as $value => $text): ?><option value="<?= e($value) ?>"<?= $old['kind'] === $value ? ' selected' : '' ?>><?= e($text) ?></option><?php endforeach; ?>
		</select>
	</label>
	<label class="field">Esporte
		<select name="sport">
			<option value="">—</option>
			<?php foreach (Goals::SPORTS as $value => $text): ?><option value="<?= e($value) ?>"<?= $old['sport'] === $value ? ' selected' : '' ?>><?= e($text) ?></option><?php endforeach; ?>
		</select>
	</label>
</div>
<label class="field">Nome<input type="text" name="name" value="<?= e($old['name']) ?>" maxlength="150" placeholder="Nome da prova ou do objetivo"></label>
<div class="row">
	<label class="field">Data de início (obrigatória para prova)<input type="date" name="target_date" value="<?= e($old['target_date']) ?>"></label>
	<label class="field">Data de fim (opcional)<input type="date" name="end_date" value="<?= e($old['end_date']) ?>"></label>
</div>
<div class="row">
	<label class="field">Distância<input type="text" name="distance" value="<?= e($old['distance']) ?>" maxlength="60"></label>
	<label class="field">Cidade<input type="text" name="city" value="<?= e($old['city']) ?>" maxlength="100"></label>
</div>
<label class="field">Observações<input type="text" name="details" value="<?= e($old['details']) ?>" maxlength="500"></label>
