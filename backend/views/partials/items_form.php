<?php
/** Grade de linhas do treino (compartilhada por treino e modelo).
 *  @var list<array<string,mixed>> $items @var string $discipline @var array $errors @var bool $isNew */
use Correon\Workout;
$strength = Workout::isStrength($discipline);
$val = static fn (array $row, string $k) => e((string) ($row[$k] ?? ''));
$blank = $isNew || !$items ? 5 : 3;
$rows = array_values($items);
for ($i = 0; $i < $blank; $i++) { $rows[] = []; }
?>
<h2><?= $strength ? 'Exercícios' : 'Etapas' ?></h2>
<?php if (!empty($errors['items'])): ?><p class="field__err"><?= e($errors['items']) ?></p><?php endif; ?>
<div class="items <?= $strength ? 'items--strength' : 'items--endurance' ?>" data-items>
	<div class="items__head" aria-hidden="true">
		<?php if ($strength): ?><span>Exercício</span><span>Séries</span><span>Repetições</span><span>Carga</span><span>Intervalo (s)</span><span>Observação</span>
		<?php else: ?><span>Etapa</span><span>Duração (min)</span><span>Distância (km)</span><span>Alvo (ritmo/zona)</span><span>Observação</span><?php endif; ?>
	</div>
	<?php foreach ($rows as $i => $row): ?>
		<div class="items__row" data-row>
			<?php if ($strength): ?>
				<input type="text" name="items[<?= $i ?>][label]" value="<?= $val($row, 'label') ?>" maxlength="120" aria-label="Exercício">
				<input type="text" inputmode="numeric" name="items[<?= $i ?>][sets]" value="<?= $val($row, 'sets') ?>" maxlength="2" aria-label="Séries">
				<input type="text" name="items[<?= $i ?>][reps]" value="<?= $val($row, 'reps') ?>" maxlength="20" aria-label="Repetições">
				<input type="text" name="items[<?= $i ?>][load_text]" value="<?= $val($row, 'load_text') ?>" maxlength="30" aria-label="Carga">
				<input type="text" inputmode="numeric" name="items[<?= $i ?>][rest_seconds]" value="<?= $val($row, 'rest_seconds') ?>" maxlength="4" aria-label="Intervalo em segundos">
				<input type="text" name="items[<?= $i ?>][notes]" value="<?= $val($row, 'notes') ?>" maxlength="300" aria-label="Observação">
			<?php else: ?>
				<input type="text" name="items[<?= $i ?>][label]" value="<?= $val($row, 'label') ?>" maxlength="120" aria-label="Etapa">
				<input type="text" inputmode="numeric" name="items[<?= $i ?>][duration_minutes]" value="<?= $val($row, 'duration_minutes') ?>" maxlength="4" aria-label="Duração em minutos">
				<input type="text" inputmode="decimal" name="items[<?= $i ?>][distance_km]" value="<?= $val($row, 'distance_km') ?>" maxlength="8" aria-label="Distância em km">
				<input type="text" name="items[<?= $i ?>][target]" value="<?= $val($row, 'target') ?>" maxlength="120" aria-label="Alvo">
				<input type="text" name="items[<?= $i ?>][notes]" value="<?= $val($row, 'notes') ?>" maxlength="300" aria-label="Observação">
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>
<p><button class="btn btn--sm btn--ghost" type="button" data-add-row hidden>+ Adicionar linha</button></p>
