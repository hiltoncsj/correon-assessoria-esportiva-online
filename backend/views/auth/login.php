<section class="card">
	<h1>Entrar</h1>
	<?php if (!empty($error)): ?><p class="notice notice--err" role="alert"><?= e($error) ?></p><?php endif; ?>
	<form method="post" action="/entrar" novalidate>
		<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
		<label class="field">E-mail
			<input type="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
		</label>
		<label class="field">Senha
			<input type="password" name="password" autocomplete="current-password" required>
		</label>
		<button class="btn" type="submit">Entrar</button>
	</form>
	<p class="links"><a href="/recuperar-senha">Esqueci minha senha</a> · <a href="/cadastro">Criar conta</a></p>
	<p class="links"><a href="<?= e(\Correon\View::site()) ?>">← Voltar para o site</a></p>
</section>
