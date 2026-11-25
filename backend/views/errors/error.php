<section class="card">
	<p class="eyebrow"><?= (int) $code ?></p>
	<h1><?= e($title) ?></h1>
	<p><?= e($msg) ?></p>
	<p><a class="btn" href="<?= e(\Correon\View::site()) ?>">Voltar ao início</a></p>
</section>
