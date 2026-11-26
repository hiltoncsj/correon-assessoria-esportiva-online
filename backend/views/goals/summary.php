<?php /** @var array<string,mixed> $g */
use Correon\{Goals, View};
?>
<strong><?= e($g['name']) ?></strong>
<span class="badge"><?= e(Goals::KINDS[$g['kind']] ?? $g['kind']) ?></span>
<span class="badge"><?= e(Goals::SPORTS[$g['sport']] ?? $g['sport']) ?></span>
<span class="muted"><?= e(implode(' · ', array_filter([$g['target_date'] ? View::dateBr($g['target_date']) : 'Sem data', $g['distance'], $g['city'], $g['details']]))) ?></span>
