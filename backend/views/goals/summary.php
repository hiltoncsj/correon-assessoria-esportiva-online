<?php
/** @var array<string,mixed> $g  Um objetivo, um item por linha com rótulo. */
use Correon\{Goals, View};

$facts = [
	'Tipo' => Goals::KINDS[$g['kind']] ?? $g['kind'],
	'Esporte' => Goals::SPORTS[$g['sport']] ?? $g['sport'],
	'Data de início' => $g['target_date'] ? View::dateBr($g['target_date']) : 'Sem data',
	'Data de fim' => $g['end_date'] ? View::dateBr($g['end_date']) : null,
	'Distância' => $g['distance'],
	'Cidade' => $g['city'],
	'Observações' => $g['details'],
	'Inscritos' => isset($g['total']) ? (string) (int) $g['total'] : null,
	'Resultado' => !empty($showPersonal) ? $g['result'] : null,
];
?>
<h3 class="goal__name"><?= e($g['name']) ?></h3>
<dl class="facts goal__facts">
	<?php foreach ($facts as $label => $value): if ($value === null || $value === '') { continue; } ?>
		<dt><?= e($label) ?></dt><dd><?= e($value) ?></dd>
	<?php endforeach; ?>
</dl>
