<?php /** @var string $title @var string $content @var ?array $flash @var ?array $user @var string $csrf */ ?>
<!doctype html>
<html lang="pt-BR">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?= e($title) ?> — CorreON</title>
	<link rel="icon" href="/favicon.svg" type="image/svg+xml">
	<link rel="stylesheet" href="/assets/tokens.css">
	<link rel="stylesheet" href="/assets/app.css">
</head>
<body>
	<a class="skip" href="#conteudo">Pular para o conteúdo</a>
	<header class="bar">
		<a class="bar__brand" href="/" aria-label="CorreON — página inicial"><img src="/assets/logo-correon.png" alt="CorreON" height="40"></a>
		<?php if ($user): ?>
			<form class="bar__user" method="post" action="/sair">
				<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
				<span><?= e($user['name']) ?></span>
				<button class="btn btn--ghost" type="submit">Sair</button>
			</form>
		<?php endif; ?>
	</header>
	<main id="conteudo" class="wrap" tabindex="-1">
		<?php if (!empty($flash)): ?>
			<p class="notice notice--<?= e($flash['type']) ?>" role="status"><?= e($flash['msg']) ?></p>
		<?php endif; ?>
		<?= $content ?>
	</main>
</body>
</html>
