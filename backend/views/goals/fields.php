<?php /** @var array<string,string> $old */
use Correon\Goals;
$select = static function (string $name, array $options, string $current, bool $blank = false): void { ?>
	<select name="<?= e($name) ?>">
		<?php if ($blank): ?><option value="">—</option><?php endif; ?>
		<?php foreach ($options as $value => $text): ?><option value="<?= e($value) ?>"<?= $current === $value ? ' selected' : '' ?>><?= e($text) ?></option><?php endforeach; ?>
	</select>
<?php };
?>
<div class="row">
	<label class="field">Tipo <?php $select('kind', Goals::KINDS, $old['kind']); ?></label>
	<label class="field">Esporte <?php $select('sport', Goals::SPORTS, $old['sport'], true); ?></label>
</div>
<label class="field">Nome<input type="text" name="name" value="<?= e($old['name']) ?>" maxlength="150" placeholder="Nome da prova ou do objetivo"></label>
<div class="row">
	<label class="field">Data (obrigatória para prova)<input type="date" name="target_date" value="<?= e($old['target_date']) ?>"></label>
	<label class="field">Distância<input type="text" name="distance" value="<?= e($old['distance']) ?>" maxlength="60"></label>
</div>
<div class="row">
	<label class="field">Cidade<input type="text" name="city" value="<?= e($old['city']) ?>" maxlength="100"></label>
	<label class="field">Meta<input type="text" name="target" value="<?= e($old['target']) ?>" maxlength="150"></label>
</div>
<div class="row">
	<label class="field">Situação <?php $select('status', Goals::STATUSES, $old['status']); ?></label>
	<label class="field">Resultado (tempo, observações)<input type="text" name="result" value="<?= e($old['result']) ?>" maxlength="500"></label>
</div>
