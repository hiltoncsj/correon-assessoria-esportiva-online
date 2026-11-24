<?php /** @var array $u @var array $meta @var int $completeness */
$first = explode(' ', trim($u['name']))[0];
$fmt = static fn (?string $d, string $f) => $d ? date($f, strtotime($d)) : '—';
?>
<p class="eyebrow">Atleta</p>
<h1>Olá, <?= e($first) ?></h1>

<div class="grid">
	<section class="tile" aria-labelledby="t-conta">
		<h2 id="t-conta">Minha conta</h2>
		<dl class="facts">
			<dt>E-mail</dt><dd><?= e($u['email']) ?></dd>
			<dt>Membro desde</dt><dd><?= e($fmt($meta['created_at'] ?? null, 'd/m/Y')) ?></dd>
			<dt>Último acesso</dt><dd><?= e($fmt($meta['last_login_at'] ?? null, 'd/m/Y H:i')) ?></dd>
		</dl>
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
		<p class="muted">Você ainda não tem modalidades ativas. A contratação pela plataforma será liberada em breve.</p>
	</section>

	<section class="tile" aria-labelledby="t-treinos">
		<h2 id="t-treinos">Treinos programados</h2>
		<p class="muted">Nenhum treino programado. Os treinos aparecerão aqui quando seu treinador publicá-los.</p>
	</section>

	<section class="tile" aria-labelledby="t-trn">
		<h2 id="t-trn">Meu treinador</h2>
		<p class="muted">Nenhum treinador atribuído ainda.</p>
	</section>

	<section class="tile" aria-labelledby="t-ass">
		<h2 id="t-ass">Assinatura</h2>
		<p class="muted">Nenhuma assinatura ativa.</p>
	</section>
</div>
