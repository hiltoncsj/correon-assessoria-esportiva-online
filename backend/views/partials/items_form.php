<?php
/** Grade de linhas (treino avulso ou sessão de modelo).
 *  @var list<array<string,mixed>> $items @var string $discipline @var array $errors @var bool $isNew @var string $prefix (nome base dos campos) */
use Correon\Workout;
$prefix = $prefix ?? 'items';
$strength = Workout::isStrength($discipline);
$val = static fn (array $row, string $k) => e((string) ($row[$k] ?? ''));
$blank = $isNew || !$items ? 5 : 3;
$rows = array_values($items);
for ($ri = 0; $ri < $blank; $ri++) { $rows[] = []; }
?>
<div data-block>
<?php if ($prefix === 'items'): ?><h2><?= $strength ? 'Exercícios' : 'Etapas' ?></h2><?php endif; ?>
<?php if ($prefix === 'items' && !empty($errors['items'])): ?><p class="field__err"><?= e($errors['items']) ?></p><?php endif; ?>
<div class="items <?= $strength ? 'items--strength' : 'items--endurance' ?>" data-items>
	<div class="items__head" aria-hidden="true">
		<?php if ($strength): ?><span><?= 'Exercício' ?></span><span>Séries</span><span>Repetições</span><span>Carga</span><span>Intervalo (s)</span><span>Observação</span>
		<?php else: ?><span>Etapa</span><span>Duração (min)</span><span>Distância (km)</span><span>Zona de FC</span><span>Alvo (pace)</span><span>Observação</span><?php endif; ?>
	</div>
	<?php foreach ($rows as $ri => $row): $n = $prefix . '[' . $ri . ']'; ?>
		<div class="items__row" data-row>
			<?php if ($strength): ?>
				<input type="text" name="<?= e($n) ?>[label]" value="<?= $val($row, 'label') ?>" maxlength="120" aria-label="Exercício">
				<input type="text" inputmode="numeric" name="<?= e($n) ?>[sets]" value="<?= $val($row, 'sets') ?>" maxlength="2" aria-label="Séries">
				<input type="text" name="<?= e($n) ?>[reps]" value="<?= $val($row, 'reps') ?>" maxlength="20" aria-label="Repetições">
				<input type="text" name="<?= e($n) ?>[load_text]" value="<?= $val($row, 'load_text') ?>" maxlength="30" aria-label="Carga">
				<input type="text" inputmode="numeric" name="<?= e($n) ?>[rest_seconds]" value="<?= $val($row, 'rest_seconds') ?>" maxlength="4" aria-label="Intervalo em segundos">
				<input type="text" name="<?= e($n) ?>[notes]" value="<?= $val($row, 'notes') ?>" maxlength="300" aria-label="Observação">
			<?php else: ?>
				<input type="text" name="<?= e($n) ?>[label]" value="<?= $val($row, 'label') ?>" maxlength="120" aria-label="Etapa">
				<input type="text" inputmode="numeric" name="<?= e($n) ?>[duration_minutes]" value="<?= $val($row, 'duration_minutes') ?>" maxlength="4" aria-label="Duração em minutos">
				<input type="text" inputmode="decimal" name="<?= e($n) ?>[distance_km]" value="<?= $val($row, 'distance_km') ?>" maxlength="8" aria-label="Distância em km">
				<select name="<?= e($n) ?>[hr_zone]" aria-label="Zona de frequência cardíaca">
					<option value="">—</option>
					<?php for ($z = 1; $z <= Workout::ZONES; $z++): ?><option value="<?= $z ?>"<?= (string) ($row['hr_zone'] ?? '') === (string) $z ? ' selected' : '' ?>>Z<?= $z ?></option><?php endfor; ?>
				</select>
				<input type="text" name="<?= e($n) ?>[target]" value="<?= $val($row, 'target') ?>" maxlength="120" aria-label="Alvo (pace)">
				<input type="text" name="<?= e($n) ?>[notes]" value="<?= $val($row, 'notes') ?>" maxlength="300" aria-label="Observação">
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>
<p><button class="btn btn--sm btn--ghost" type="button" data-add-row hidden>+ Adicionar linha</button></p>
</div>
