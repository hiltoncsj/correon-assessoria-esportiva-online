<?php /** @var array $u @var array $meta @var int $completeness @var array $next @var array $stats @var array $trainers */
$first = explode(' ', trim($u['name']))[0];
$fmt = static fn (?string $d, string $f) => $d ? date($f, strtotime($d)) : '—';
?>
<p class="eyebrow">Atleta</p>
<h1>Olá, <?= e($first) ?></h1>

<div class="grid">
	<section class="tile" aria-labelledby="t-treinos">
		<h2 id="t-treinos">Próximos treinos</h2>
		<?php if (!$next): ?>
			<p class="muted">Nenhum treino programado. Os treinos aparecerão aqui quando seu treinador publicá-los.</p>
		<?php else: ?>
			<ul class="agenda agenda--compact">
				<?php foreach ($next as $n): ?>
					<li class="agenda__item"><a href="/app/treinos/<?= (int) $n['id'] ?>">
						<span class="agenda__date"><?= e(\Correon\View::dateBr($n['scheduled_date'])) ?></span>
						<span class="agenda__title"><?= e($n['title']) ?></span>
						<span class="muted"><?= e($n['modality']) ?></span>
					</a></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<p class="muted"><?= (int) $stats['upcoming'] ?> treino(s) programado(s) · <?= (int) $stats['done'] ?> realizado(s)</p>
		<p><a class="btn btn--sm" href="/app/treinos">Ver todos os treinos</a></p>
	</section>

	<?php if ($appointments): ?>
	<section class="tile" aria-labelledby="t-comp">
		<h2 id="t-comp">Próximos compromissos</h2>
		<ul class="plain-list">
			<?php foreach ($appointments as $a): ?><li><?= e(\Correon\View::dateBr(substr($a['starts_at'], 0, 10))) ?> às <?= e(substr($a['starts_at'], 11, 5)) ?> — Personal com <?= e($a['trainer']) ?></li><?php endforeach; ?>
		</ul>
		<p><a class="btn btn--sm" href="/app/personal">Ver agenda</a></p>
	</section>
	<?php endif; ?>

	<section class="tile" aria-labelledby="t-trn">
		<h2 id="t-trn">Meu treinador</h2>
		<?php if (!$trainers): ?>
			<p class="muted">Nenhum treinador atribuído ainda.</p>
		<?php else: ?>
			<ul class="facts">
				<?php foreach ($trainers as $t): ?><li><?= e($t['trainer']) ?> — <?= e($t['modality']) ?></li><?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>

	<section class="tile" aria-labelledby="t-perfil">
		<h2 id="t-perfil">Meu perfil</h2>
		<p class="meter-label"><?= (int) $completeness ?>% preenchido</p>
		<div class="meter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $completeness ?>" aria-label="Perfil preenchido">
			<span class="meter__bar meter--<?= (int) (round($completeness / 10) * 10) ?>"></span>
		</div>
		<p><a class="btn btn--sm" href="/app/perfil"><?= $completeness < 100 ? 'Completar perfil' : 'Ver perfil' ?></a></p>
	</section>

	<section class="tile" aria-labelledby="t-mod">
		<h2 id="t-mod">Modalidades ativas</h2>
		<?php if (!$modalities): ?>
			<p class="muted">Você não tem modalidades com contrato vigente.</p>
		<?php else: ?>
			<ul class="plain-list"><?php foreach ($modalities as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul>
		<?php endif; ?>
	</section>

	<section class="tile" aria-labelledby="t-ass">
		<h2 id="t-ass">Assinatura</h2>
		<?php $current = array_values(array_filter($contracts, static fn ($c) => in_array(\Correon\Billing::label($c), ['Vigente', 'A iniciar'], true)));
		$late = array_sum(array_column($contracts, 'overdue')); ?>
		<?php if (!$current && !$late): ?>
			<p class="muted">Nenhuma assinatura ativa.</p>
		<?php else: ?>
			<?php foreach ($current as $c): ?><p><?= e(\Correon\Billing::PRODUCTS[$c['product']] ?? $c['product']) ?> · <?= e(\Correon\Billing::PERIODS[$c['period']]) ?> até <?= e(date('d/m/Y', strtotime($c['ends_on']))) ?></p><?php endforeach; ?>
			<?php if ($late): ?><p><span class="badge badge--off"><?= (int) $late ?> parcela(s) em atraso</span></p><?php endif; ?>
		<?php endif; ?>
		<p><a class="btn btn--sm" href="/app/assinaturas">Ver pagamentos</a></p>
	</section>

	<section class="tile" aria-labelledby="t-conta">
		<h2 id="t-conta">Minha conta</h2>
		<dl class="facts">
			<dt>E-mail</dt><dd><?= e($u['email']) ?></dd>
			<dt>Membro desde</dt><dd><?= e($fmt($meta['created_at'] ?? null, 'd/m/Y')) ?></dd>
			<dt>Último acesso</dt><dd><?= e($fmt($meta['last_login_at'] ?? null, 'd/m/Y H:i')) ?></dd>
		</dl>
	</section>
</div>
