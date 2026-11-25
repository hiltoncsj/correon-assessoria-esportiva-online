<?php /** @var list<array<string,mixed>> $items @var string $discipline  @var array<int,array{min:int,max:int}> $zones (opcional) */
use Correon\Workout;
$zones = $zones ?? [];
$text = static fn (string $k) => static fn (array $it): ?string => ($it[$k] ?? null) === null || $it[$k] === '' ? null : (string) $it[$k];
$suffix = static fn (string $k, string $unit) => static fn (array $it): ?string => ($it[$k] ?? null) === null ? null : $it[$k] . $unit;
if ($discipline === 'musculacao') {
	$cols = [
		'Exercício' => $text('label'), 'Séries' => $text('sets'), 'Repetições' => $text('reps'), 'Carga' => $text('load_text'),
		'Intervalo' => $suffix('rest_seconds', ' s'), 'Observação' => $text('notes'),
	];
} else {
	$cols = [
		'Etapa' => $text('label'),
		'Duração' => $suffix('duration_minutes', ' min'),
		'Distância' => static fn (array $it): ?string => ($it['distance_km'] ?? null) === null ? null
			: rtrim(rtrim(number_format((float) $it['distance_km'], 2, ',', ''), '0'), ',') . ' km',
		'Zona de FC' => static fn (array $it): ?string => ($it['hr_zone'] ?? null) === null ? null : Workout::zoneLabel((int) $it['hr_zone'], $zones),
		'Alvo' => $text('target'), 'Observação' => $text('notes'),
	];
}
// Colunas sem nenhum valor não são mostradas (evita tabela larga e cortada).
$first = array_key_first($cols);
$cols = array_filter($cols, static fn ($get, $label) => $label === $first || array_filter($items, static fn ($it) => $get($it) !== null), ARRAY_FILTER_USE_BOTH);
?>
<?php if (!$items): ?>
	<p class="muted">Este treino ainda não tem linhas.</p>
<?php else: ?>
<div class="tbl-wrap tbl-wrap--items">
	<table class="tbl tbl--items">
		<thead>
			<tr><?php foreach ($cols as $label => $get): ?><th><?= e($label) ?></th><?php endforeach; ?></tr>
		</thead>
		<tbody>
		<?php foreach ($items as $it): ?>
			<tr>
				<?php foreach ($cols as $label => $get): $v = $get($it); ?>
					<td data-label="<?= e($label) ?>"<?= $v === null ? ' class="is-empty"' : '' ?>><?= $v === null ? '—' : e($v) ?></td>
				<?php endforeach; ?>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php endif; ?>
