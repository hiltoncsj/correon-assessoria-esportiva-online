<section class="card">
	<h1>Recuperar senha</h1>
	<?php if (!empty($sent)): ?>
		<p class="notice notice--ok" role="status"><?= e($sent) ?></p>
	<?php else: ?>
		<?php if (!empty($error)): ?><p class="notice notice--err" role="alert"><?= e($error) ?></p><?php endif; ?>
		<form method="post" action="/recuperar-senha" novalidate>
			<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
			<label class="field">E-mail cadastrado
				<input type="email" name="email" autocomplete="email" required autofocus>
			</label>
			<button class="btn" type="submit">Enviar instruções</button>
		</form>
	<?php endif; ?>
	<p class="links"><a href="/entrar">Voltar para entrar</a></p>
	<p class="links"><a href="<?= e(\Correon\View::site()) ?>">← Voltar para o site</a></p>
</section>
