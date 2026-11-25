<?php /** @var array $pending @var array $approved @var array $history @var bool $hasSlots */
use Correon\Personal;
$dt = static fn (string $v) => \Correon\View::dateBr(substr($v, 0, 10)) . ' às ' . substr($v, 11, 5);
$now = date('Y-m-d H:i:s');
?>
<p class="eyebrow">Treinador</p>
<h1>Personal Trainer</h1>
<?php if (!$hasSlots): ?><p class="notice">Você não tem horários presenciais cadastrados, então os alunos não conseguem pedir. <a href="/treinador/disponibilidade">Cadastrar disponibilidade</a></p><?php endif; ?>

<section class="tile" aria-labelledby="h-pend">
	<h2 id="h-pend">Pedidos aguardando você (<?= count($pending) ?>)</h2>
	<?php if (!$pending): ?><p class="muted">Nenhum pedido pendente.</p><?php else: ?>
		<ul class="done-list">
			<?php foreach ($pending as $s): ?>
				<li>
					<strong><?= e($dt($s['starts_at'])) ?></strong> <?= e($s['athlete']) ?>
					<form class="form--inline" method="post" action="/treinador/personal/<?= (int) $s['id'] ?>">
						<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
						<input type="text" class="select" name="note" maxlength="300" placeholder="observação (opcional)" aria-label="Observação para <?= e($s['athlete']) ?>">
						<button class="btn btn--sm" type="submit" name="action" value="approve">Confirmar</button>
						<button class="btn btn--sm btn--danger" type="submit" name="action" value="refuse">Recusar</button>
					</form>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>

<section class="tile" aria-labelledby="h-conf">
	<h2 id="h-conf">Sessões confirmadas (<?= count($approved) ?>)</h2>
	<?php if (!$approved): ?><p class="muted">Nenhuma sessão confirmada.</p><?php else: ?>
		<ul class="done-list">
			<?php foreach ($approved as $s): $past = $s['starts_at'] <= $now; ?>
				<li>
					<strong><?= e($dt($s['starts_at'])) ?></strong> <?= e($s['athlete']) ?><?= $s['location'] ? ' <span class="muted">' . e($s['location']) . '</span>' : '' ?>
					<form class="form--inline" method="post" action="/treinador/personal/<?= (int) $s['id'] ?>">
						<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
						<label class="check"><input type="checkbox" name="consumes" value="1"><span>descontar sessão (falta/cancelamento)</span></label>
						<?php if ($past): ?>
							<button class="btn btn--sm" type="submit" name="action" value="done">Realizada</button>
							<button class="btn btn--sm btn--ghost" type="submit" name="action" value="missed">Falta</button>
						<?php endif; ?>
						<button class="btn btn--sm btn--danger" type="submit" name="action" value="cancel">Cancelar</button>
					</form>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>

<?php if ($history): ?>
	<section class="tile" aria-labelledby="h-hist">
		<h2 id="h-hist">Histórico</h2>
		<ul class="plain-list">
			<?php foreach ($history as $s): ?><li><?= e($dt($s['starts_at'])) ?> — <?= e($s['athlete']) ?>: <?= e(Personal::STATUS[$s['status']]) ?><?= (int) $s['consumes'] && $s['status'] !== 'done' ? ' (descontada)' : '' ?></li><?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>
