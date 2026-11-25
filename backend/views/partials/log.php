<?php /** @var array $log */
use Correon\Workout;
$km = $log['distance_km'] === null ? null : rtrim(rtrim(number_format((float) $log['distance_km'], 2, ',', ''), '0'), ',');
?>
<dl class="facts">
	<dt>Situação</dt><dd><?= e(Workout::OUTCOMES[$log['outcome']]) ?></dd>
	<dt>Data</dt><dd><?= e(\Correon\View::dateBr($log['performed_on'])) ?></dd>
	<?php if ($log['duration_minutes'] !== null): ?><dt>Duração</dt><dd><?= (int) $log['duration_minutes'] ?> min</dd><?php endif; ?>
	<?php if ($km !== null): ?><dt>Distância</dt><dd><?= e($km) ?> km</dd><?php endif; ?>
	<?php if ($log['effort'] !== null): ?><dt>Esforço percebido</dt><dd><?= (int) $log['effort'] ?>/10</dd><?php endif; ?>
	<?php if ($log['notes']): ?><dt>Comentário</dt><dd><?= e($log['notes']) ?></dd><?php endif; ?>
</dl>
