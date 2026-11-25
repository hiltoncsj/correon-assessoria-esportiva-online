<?php /** @var array $p @var array $balance @var array $sessions */
use Correon\{Billing, Personal};
$d = static fn (?string $v) => $v ? date('d/m/Y', strtotime($v)) : '—';
?>
<p class="eyebrow"><a href="/admin/atletas/<?= (int) $p['athlete_id'] ?>">← <?= e($p['athlete']) ?></a></p>
<h1>Personal #<?= (int) $p['id'] ?></h1>
<section class="tile" aria-labelledby="h-p">
	<h2 id="h-p"><?= e(Personal::FORMATS[$p['format']]) ?></h2>
	<dl class="facts">
		<dt>Treinador</dt><dd><?= e($p['trainer']) ?></dd>
		<dt>Situação</dt><dd><?= $p['status'] === 'cancelled' ? 'Cancelado — ' . e((string) $p['cancel_reason']) : 'Ativo' ?></dd>
		<?php if ($p['format'] === 'mensal'): ?><dt>Frequência</dt><dd><?= (int) $p['weekly_frequency'] ?> por semana (<?= (int) $balance['used'] ?> utilizadas)</dd>
		<?php else: ?><dt>Sessões</dt><dd><?= (int) $p['sessions_total'] ?> contratadas · <?= (int) $balance['used'] ?> utilizadas · <?= (int) $balance['remaining'] ?> restantes</dd><?php endif; ?>
		<dt>Duração</dt><dd><?= (int) $p['session_minutes'] ?> min</dd>
		<dt>Local</dt><dd><?= e((string) $p['location']) ?: '—' ?></dd>
		<dt>Vigência</dt><dd><?= e($d($p['starts_on'])) ?> a <?= $p['valid_until'] ? e($d($p['valid_until'])) : 'sem validade definida' ?></dd>
		<dt>Valor</dt><dd><?= e(Billing::money($p['price_cents'])) ?><?= $p['paid_on'] ? ' · pago em ' . e($d($p['paid_on'])) . ' (' . e(Billing::METHODS[$p['method']]) . ')' : '' ?></dd>
	</dl>
	<?php if ($p['price_cents'] !== null && !$p['paid_on']): ?>
		<form class="form form--inline" method="post" action="/admin/personal/<?= (int) $p['id'] ?>/pagar">
			<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
			<label class="field">Pago em<input type="date" name="paid_on" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>"></label>
			<label class="field">Forma<select name="method"><option value="">—</option><?php foreach (Billing::METHODS as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></label>
			<button class="btn btn--sm" type="submit">Dar baixa</button>
		</form>
	<?php endif; ?>
</section>

<section class="tile" aria-labelledby="h-s">
	<h2 id="h-s">Sessões</h2>
	<?php if (!$sessions): ?><p class="muted">Nenhum pedido ainda.</p><?php else: ?>
		<ul class="plain-list">
			<?php foreach ($sessions as $s): ?><li><?= e(date('d/m/Y H:i', strtotime($s['starts_at']))) ?> — <?= e(Personal::STATUS[$s['status']]) ?><?= (int) $s['consumes'] ? ' (descontada)' : '' ?></li><?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>

<?php if ($p['status'] === 'active'): ?>
	<form class="tile form" method="post" action="/admin/personal/<?= (int) $p['id'] ?>/cancelar">
		<h2>Cancelar</h2>
		<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
		<label class="field">Motivo<input type="text" name="reason" maxlength="300"></label>
		<div class="actions"><button class="btn btn--sm btn--danger" type="submit" data-confirm="Cancelar este Personal?">Cancelar Personal</button></div>
	</form>
	<script src="<?= e(\Correon\View::asset('/assets/workout-form.js')) ?>" defer></script>
<?php endif; ?>
