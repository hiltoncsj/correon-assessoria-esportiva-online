<?php /** @var array $person @var array $capacity @var mixed $presencial @var array $athletes @var array $exceptions @var array $slots */
use Correon\Templates;
?>
<p class="eyebrow"><a href="/admin/treinadores">← Treinadores</a></p>
<h1><?= e($person['name']) ?></h1>

<section class="tile" aria-labelledby="h-cap">
	<h2 id="h-cap">Capacidade</h2>
	<p class="stat"><?= (int) $capacity['active'] ?><small> / <?= (int) $capacity['limit'] ?> atletas online (<?= (int) $capacity['pct'] ?>%)</small></p>
	<form class="form form--inline" method="post" action="/admin/treinadores/<?= (int) $person['id'] ?>/capacidade" novalidate>
		<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
		<label class="field">Limite online<input type="text" inputmode="numeric" name="online_limit" value="<?= (int) $capacity['limit'] ?>" maxlength="4" class="zones__max"></label>
		<label class="field">Limite presencial (opcional)<input type="text" inputmode="numeric" name="presencial_limit" value="<?= $presencial === null ? '' : (int) $presencial ?>" maxlength="4" class="zones__max"></label>
		<button class="btn btn--sm" type="submit">Salvar</button>
	</form>
	<p class="muted">O limite presencial só passa a ser contado com o Personal Trainer.</p>
</section>

<section class="tile" aria-labelledby="h-atl">
	<h2 id="h-atl">Atletas (<?= count($athletes) ?>)</h2>
	<?php if (!$athletes): ?><p class="muted">Nenhum atleta vinculado. Atribua pela página do atleta.</p><?php else: ?>
		<ul class="plain-list">
			<?php foreach ($athletes as $a): ?><li><a href="/admin/atletas/<?= (int) $a['id'] ?>"><?= e($a['name']) ?></a> <span class="muted"><?= e($a['modalities']) ?></span></li><?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>

<?php if ($exceptions): ?>
	<section class="tile" aria-labelledby="h-exc">
		<h2 id="h-exc">Exceções de capacidade</h2>
		<ul class="plain-list">
			<?php foreach ($exceptions as $x): ?><li><?= e(date('d/m/Y', strtotime($x['created_at']))) ?> — <?= e($x['athlete']) ?>: <?= e($x['reason']) ?></li><?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>

<section class="tile" aria-labelledby="h-disp">
	<h2 id="h-disp">Disponibilidade informada</h2>
	<?php if (!$slots): ?><p class="muted">O treinador ainda não cadastrou horários.</p><?php else: ?>
		<ul class="plain-list">
			<?php foreach ($slots as $s): ?><li><?= e(Templates::WEEKDAYS[(int) $s['weekday']]) ?>, <?= e(substr($s['start_time'], 0, 5)) ?>–<?= e(substr($s['end_time'], 0, 5)) ?> (<?= e($s['mode']) ?>)</li><?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>

<?php require BASE_PATH . '/views/partials/account.php'; ?>
