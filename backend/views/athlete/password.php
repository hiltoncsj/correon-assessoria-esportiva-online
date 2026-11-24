<?php /** @var ?string $error */ ?>
<p class="eyebrow">Atleta</p>
<h1>Alterar senha</h1>
<form class="tile form form--narrow" method="post" action="/app/senha" novalidate>
	<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
	<?php if (!empty($error)): ?><p class="notice notice--err" role="alert"><?= e($error) ?></p><?php endif; ?>
	<label class="field">Nova senha (mínimo 10 caracteres)
		<input type="password" name="password" autocomplete="new-password" minlength="10" required autofocus>
	</label>
	<label class="field">Repita a nova senha
		<input type="password" name="password2" autocomplete="new-password" required>
	</label>
	<p class="muted">Ao alterar a senha, os outros dispositivos conectados serão desconectados e enviaremos um aviso para o seu e-mail.</p>
	<button class="btn" type="submit">Alterar senha</button>
</form>
