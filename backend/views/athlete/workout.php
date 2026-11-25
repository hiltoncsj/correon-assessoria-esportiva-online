<?php /** @var array $w @var array $items @var ?array $log @var array $errors @var array $old @var bool $canLog */
use Correon\Workout;
$discipline = $w['discipline'];
$err = static fn (string $k) => !empty($errors[$k]) ? '<span class="field__err">' . e($errors[$k]) . '</span>' : '';
$o = static fn (string $k, string $d = '') => e((string) ($old[$k] ?? $d));
$strength = Workout::isStrength($discipline);
?>
<p class="eyebrow"><a href="/app/treinos?mes=<?= e(substr($w['scheduled_date'], 0, 7)) ?>">← Meus treinos</a></p>
<h1><?= e($w['title']) ?></h1>
<p>
	<span class="badge"><?= e($w['modality_name']) ?><?= Workout::DISCIPLINES[$discipline] !== $w['modality_name'] ? ' · ' . e(Workout::DISCIPLINES[$discipline]) : '' ?></span>
	<span class="muted"><?= e(\Correon\View::dateBr($w['scheduled_date'])) ?></span>
</p>

<section class="tile" aria-labelledby="plano">
	<h2 id="plano">Treino planejado</h2>
	<?php if ($w['notes']): ?><p><?= nl2br(e($w['notes'])) ?></p><?php endif; ?>
	<?php require BASE_PATH . '/views/partials/items.php'; ?>
</section>

<section class="tile" aria-labelledby="real">
	<h2 id="real">Treino realizado</h2>
	<?php if ($log && !$errors): ?>
		<?php require BASE_PATH . '/views/partials/log.php'; ?>
	<?php endif; ?>
	<?php if (!$canLog): ?>
		<p class="muted">Você poderá registrar este treino a partir do dia dele.</p>
	<?php else: ?>
		<?php if ($log && !$errors): ?><h3>Corrigir registro</h3><?php endif; ?>
		<form class="form" method="post" action="/app/treinos/<?= (int) $w['id'] ?>/registro" novalidate>
			<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
			<div class="row">
				<label class="field">Como foi?
					<select name="outcome" required>
						<?php foreach (Workout::OUTCOMES as $k => $label): ?>
							<option value="<?= e($k) ?>"<?= ($old['outcome'] ?? 'completed') === $k ? ' selected' : '' ?>><?= e($label) ?></option>
						<?php endforeach; ?>
					</select>
					<?= $err('outcome') ?>
				</label>
				<label class="field">Dia em que treinou
					<input type="date" name="performed_on" value="<?= $o('performed_on', min($w['scheduled_date'], date('Y-m-d'))) ?>" max="<?= e(date('Y-m-d')) ?>" required>
					<?= $err('performed_on') ?>
				</label>
			</div>
			<div class="row">
				<label class="field">Duração (min)
					<input type="text" inputmode="numeric" name="duration_minutes" value="<?= $o('duration_minutes') ?>" maxlength="4">
					<?= $err('duration_minutes') ?>
				</label>
				<?php if (!$strength): ?>
					<label class="field">Distância (km)
						<input type="text" inputmode="decimal" name="distance_km" value="<?= $o('distance_km') ?>" maxlength="8">
						<?= $err('distance_km') ?>
					</label>
				<?php endif; ?>
				<label class="field">Esforço percebido (1 a 10)
					<input type="text" inputmode="numeric" name="effort" value="<?= $o('effort') ?>" maxlength="2">
					<?= $err('effort') ?>
				</label>
			</div>
			<label class="field">Comentário (opcional)
				<textarea name="notes" rows="3" maxlength="500"><?= $o('notes') ?></textarea>
				<?= $err('notes') ?>
			</label>
			<p class="muted">Não informe dados de saúde neste campo.</p>
			<button class="btn" type="submit"><?= $log ? 'Salvar correção' : 'Salvar registro' ?></button>
		</form>
	<?php endif; ?>
</section>
