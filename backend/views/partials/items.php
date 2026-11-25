<?php /** @var list<array<string,mixed>> $items @var string $discipline  @var array<int,array{min:int,max:int}> $zones (opcional) */
use Correon\Workout;
$zones = $zones ?? [];
$strength = $discipline === 'musculacao';
$dash = static fn ($v) => ($v === null || $v === '') ? '—' : e((string) $v);
$km = static fn ($v) => $v === null ? '—' : e(rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ','));
?>
<?php if (!$items): ?>
	<p class="muted">Este treino ainda não tem linhas.</p>
<?php else: ?>
<div class="tbl-wrap">
	<table class="tbl">
		<thead>
			<?php if ($strength): ?>
				<tr><th>Exercício</th><th>Séries</th><th>Repetições</th><th>Carga</th><th>Intervalo</th><th>Observação</th></tr>
			<?php else: ?>
				<tr><th>Etapa</th><th>Duração</th><th>Distância</th><th>Zona de FC</th><th>Alvo</th><th>Observação</th></tr>
			<?php endif; ?>
		</thead>
		<tbody>
		<?php foreach ($items as $it): ?>
			<?php if ($strength): ?>
				<tr><td><?= e($it['label']) ?></td><td><?= $dash($it['sets']) ?></td><td><?= $dash($it['reps']) ?></td><td><?= $dash($it['load_text']) ?></td>
				<td><?= $it['rest_seconds'] === null ? '—' : e((string) $it['rest_seconds']) . ' s' ?></td><td><?= $dash($it['notes']) ?></td></tr>
			<?php else: ?>
				<tr><td><?= e($it['label']) ?></td><td><?= $it['duration_minutes'] === null ? '—' : e((string) $it['duration_minutes']) . ' min' ?></td>
				<td><?= $it['distance_km'] === null ? '—' : $km($it['distance_km']) . ' km' ?></td>
				<td><?= e(Workout::zoneLabel(isset($it['hr_zone']) && $it['hr_zone'] !== null ? (int) $it['hr_zone'] : null, $zones)) ?></td>
				<td><?= $dash($it['target']) ?></td><td><?= $dash($it['notes']) ?></td></tr>
			<?php endif; ?>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php endif; ?>
