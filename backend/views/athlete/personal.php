<?php /** @var array $packages @var array $sessions */
use Correon\{Personal, Templates};
$dt = static fn (string $v) => \Correon\View::dateBr(substr($v, 0, 10)) . ' às ' . substr($v, 11, 5);
$active = array_filter($packages, static fn ($p) => $p['status'] === 'active' && ($p['valid_until'] === null || $p['valid_until'] >= date('Y-m-d')));
?>
<p class="eyebrow">Atleta</p>
<h1>Personal Trainer</h1>
<?php if (!$active): ?>
	<div class="tile"><p class="muted">Você não tem pacote de Personal ativo. A contratação é feita com a CorreON.</p></div>
<?php endif; ?>

<?php foreach ($active as $p): $b = $p['balance']; ?>
	<section class="tile" aria-label="Pacote <?= (int) $p['id'] ?>">
		<h2><?= e(Personal::FORMATS[$p['format']]) ?> · <?= e($p['trainer']) ?></h2>
		<dl class="facts">
			<?php if ($p['format'] === 'mensal'): ?>
				<dt>Frequência</dt><dd><?= (int) $p['weekly_frequency'] ?> por semana</dd>
				<dt>Realizadas</dt><dd><?= (int) $b['used'] ?></dd>
			<?php else: ?>
				<dt>Contratadas</dt><dd><?= (int) $p['sessions_total'] ?></dd>
				<dt>Utilizadas</dt><dd><?= (int) $b['used'] ?></dd>
				<dt>Restantes</dt><dd><?= (int) $b['remaining'] ?><?= $b['booked'] ? ' (' . (int) $b['booked'] . ' agendada(s))' : '' ?></dd>
			<?php endif; ?>
			<dt>Duração</dt><dd><?= (int) $p['session_minutes'] ?> min</dd>
			<?php if ($p['location']): ?><dt>Local</dt><dd><?= e($p['location']) ?></dd><?php endif; ?>
			<?php if ($p['valid_until']): ?><dt>Válido até</dt><dd><?= e(date('d/m/Y', strtotime($p['valid_until']))) ?></dd><?php endif; ?>
		</dl>
		<h3>Pedir um horário</h3>
		<?php if (!$p['slots']): ?>
			<p class="muted">O treinador ainda não informou horários presenciais.</p>
		<?php else: ?>
			<p class="muted">Horários do treinador:
				<?php foreach ($p['slots'] as $s): ?><?= e(Templates::WEEKDAYS[(int) $s['weekday']]) ?> <?= e(substr($s['start_time'], 0, 5)) ?>–<?= e(substr($s['end_time'], 0, 5)) ?>; <?php endforeach; ?></p>
			<form class="form form--inline" method="post" action="/app/personal" novalidate>
				<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
				<input type="hidden" name="package_id" value="<?= (int) $p['id'] ?>">
				<label class="field">Dia<input type="date" name="date" min="<?= e(date('Y-m-d')) ?>"></label>
				<label class="field">Início<input type="time" name="time"></label>
				<button class="btn btn--sm" type="submit">Pedir horário</button>
			</form>
		<?php endif; ?>
	</section>
<?php endforeach; ?>

<?php if ($sessions): ?>
	<h2>Minhas sessões</h2>
	<ul class="done-list tile">
		<?php foreach ($sessions as $s): ?>
			<li>
				<strong><?= e($dt($s['starts_at'])) ?></strong> <span class="muted"><?= e($s['trainer']) ?></span>
				<span class="badge <?= $s['status'] === 'approved' || $s['status'] === 'done' ? 'badge--ok' : ($s['status'] === 'requested' ? 'badge--draft' : '') ?>"><?= e(Personal::STATUS[$s['status']]) ?></span>
				<?php if ((int) $s['consumes'] && $s['status'] !== 'done'): ?><span class="muted">sessão descontada</span><?php endif; ?>
				<?php if ($s['note']): ?><span class="muted">“<?= e($s['note']) ?>”</span><?php endif; ?>
				<?php if ($s['status'] === 'requested'): ?>
					<form method="post" action="/app/personal/<?= (int) $s['id'] ?>/desistir">
						<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
						<button class="btn btn--sm btn--danger" type="submit">Desistir</button>
					</form>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
