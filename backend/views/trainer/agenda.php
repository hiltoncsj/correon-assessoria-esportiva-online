<?php /** @var array<string,array> $days @var string $monday @var string $sunday @var string $prev @var string $next */
use Correon\Workout;
$fmt = static fn (string $d) => date('d/m', strtotime($d));
?>
<p class="eyebrow">Treinador</p>
<h1>Agenda</h1>
<nav class="monthnav" aria-label="Semana">
	<a class="btn btn--sm btn--ghost" href="/treinador/agenda?semana=<?= e($prev) ?>">← Semana anterior</a>
	<strong>Semana de <?= e($fmt($monday)) ?> a <?= e($fmt($sunday)) ?></strong>
	<a class="btn btn--sm btn--ghost" href="/treinador/agenda?semana=<?= e($next) ?>">Próxima semana →</a>
</nav>
<?php foreach ($days as $date => $list): ?>
	<section class="tile" aria-label="<?= e(\Correon\View::dateBr($date)) ?>">
		<h2><?= e(\Correon\View::dateBr($date)) ?></h2>
		<?php if (!empty($personal[$date])): ?>
			<ul class="plain-list">
				<?php foreach ($personal[$date] as $s): ?><li><a href="/treinador/personal"><strong><?= e(substr($s['starts_at'], 11, 5)) ?></strong> Personal — <?= e($s['athlete']) ?></a> <span class="badge badge--ok"><?= e(\Correon\Personal::STATUS[$s['status']]) ?></span></li><?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if (!$list): ?>
			<p class="muted">Sem treinos prescritos.</p>
		<?php else: ?>
			<ul class="plain-list">
				<?php foreach ($list as $w): ?>
					<li>
						<a href="/treinador/treinos/<?= (int) $w['id'] ?>"><?= e($w['athlete']) ?> — <?= e($w['title']) ?></a>
						<?php if ($w['status'] === 'draft'): ?><span class="badge badge--draft">Rascunho</span>
						<?php elseif ($w['outcome']): ?><span class="badge badge--ok"><?= e(Workout::OUTCOMES[$w['outcome']]) ?></span><?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
<?php endforeach; ?>

