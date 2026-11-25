<?php
/** @var array{active:int,limit:int,pct:int,state:string} $capacity @var array $stats @var array $withoutWeek @var array $missing
 *  @var int $news @var string $monday @var string $sunday */
$fmt = static fn (string $d) => date('d/m', strtotime($d));
$bar = min(100, (int) (round($capacity['pct'] / 10) * 10));
?>
<p class="eyebrow">Treinador</p>
<h1>Início</h1>

<?php if ($capacity['state'] === 'full'): ?>
	<p class="notice notice--err" role="status">Capacidade esgotada: <?= (int) $capacity['active'] ?> de <?= (int) $capacity['limit'] ?> atletas. Novas atribuições estão bloqueadas, salvo exceção registrada pela administração.</p>
<?php elseif ($capacity['state'] === 'alert'): ?>
	<p class="notice" role="status">Atenção: você já usa <?= (int) $capacity['pct'] ?>% da sua capacidade.</p>
<?php endif; ?>

<?php if ($ptPending): ?><p class="notice"><?= (int) $ptPending ?> pedido(s) de Personal aguardando. <a href="/treinador/personal">Responder</a></p><?php endif; ?>

<div class="grid">
	<section class="tile" aria-labelledby="t-cap">
		<h2 id="t-cap">Capacidade online</h2>
		<p class="stat"><?= (int) $capacity['active'] ?><small> / <?= (int) $capacity['limit'] ?> atletas</small></p>
		<div class="meter meter--<?= e($capacity['state']) ?>" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= min(100, (int) $capacity['pct']) ?>" aria-label="Capacidade usada">
			<span class="meter__bar meter--<?= $bar ?>"></span>
		</div>
		<p class="muted">Presencial (Personal Trainer): <?= (int) $presential['active'] ?> atleta(s)<?= $presential['limit'] === null ? ', sem limite definido' : ' de ' . (int) $presential['limit'] ?>.</p>
	</section>

	<section class="tile" aria-labelledby="t-sem">
		<h2 id="t-sem">Semana de <?= e($fmt($monday)) ?> a <?= e($fmt($sunday)) ?></h2>
		<p class="stat"><?= (int) $stats['week_published'] ?><small> treino(s) publicado(s)</small></p>
		<p class="muted"><?= (int) $stats['drafts'] ?> rascunho(s) a partir desta semana.</p>
		<p><a class="btn btn--sm" href="/treinador/agenda">Ver agenda</a></p>
	</section>

	<section class="tile" aria-labelledby="t-nov">
		<h2 id="t-nov">Novidades</h2>
		<p class="stat"><?= (int) $news ?><small> registro(s) novo(s)</small></p>
		<p><a class="btn btn--sm" href="/treinador/novidades">Ver registros</a></p>
	</section>
</div>

<section class="tile" aria-labelledby="t-wo">
	<h2 id="t-wo">Sem treino nesta semana (<?= count($withoutWeek) ?>)</h2>
	<?php if (!$withoutWeek): ?>
		<p class="muted">Todos os seus atletas têm treino nesta semana.</p>
	<?php else: ?>
		<ul class="plain-list">
			<?php foreach ($withoutWeek as $a): ?><li><a href="/treinador/atletas/<?= (int) $a['id'] ?>"><?= e($a['name']) ?></a></li><?php endforeach; ?>
		</ul>
		<p><a class="btn btn--sm" href="/treinador/modelos/aplicar">Aplicar modelos</a></p>
	<?php endif; ?>
</section>

<section class="tile" aria-labelledby="t-miss">
	<h2 id="t-miss">Treinos sem registro (últimos 7 dias)</h2>
	<?php if (!$missing): ?>
		<p class="muted">Nenhum treino pendente de registro.</p>
	<?php else: ?>
		<ul class="plain-list">
			<?php foreach ($missing as $m): ?>
				<li><a href="/treinador/treinos/<?= (int) $m['id'] ?>"><?= e($m['athlete']) ?> — <?= e($m['title']) ?></a> <span class="muted"><?= e(\Correon\View::dateBr($m['scheduled_date'])) ?></span></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
