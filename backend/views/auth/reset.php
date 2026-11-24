<section class="card">
	<h1>Nova senha</h1>
	<?php if (!empty($error)): ?><p class="notice notice--err" role="alert"><?= e($error) ?></p><?php endif; ?>
	<form method="post" action="/redefinir-senha/<?= e($token) ?>" novalidate>
		<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
		<label class="field">Nova senha (mínimo 10 caracteres)
			<input type="password" name="password" autocomplete="new-password" minlength="10" required autofocus>
		</label>
		<label class="field">Repita a nova senha
			<input type="password" name="password2" autocomplete="new-password" required>
		</label>
		<button class="btn" type="submit">Salvar nova senha</button>
	</form>
</section>
