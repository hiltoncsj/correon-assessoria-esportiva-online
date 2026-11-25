<?php /** @var ?string $error */ ?>
<section class="card">
	<h1>Crie a sua senha</h1>
	<p class="muted">Você entrou com uma senha provisória. Defina a sua senha para continuar.</p>
	<?php if (!empty($error)): ?><p class="notice notice--err" role="alert"><?= e($error) ?></p><?php endif; ?>
	<form method="post" action="/trocar-senha" novalidate>
		<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
		<label class="field">Nova senha (mínimo 10 caracteres)
			<input type="password" name="password" autocomplete="new-password" minlength="10" required autofocus>
		</label>
		<label class="field">Repita a nova senha
			<input type="password" name="password2" autocomplete="new-password" required>
		</label>
		<?php if (!empty($needsTerms)): ?>
			<label class="check">
				<input type="checkbox" name="accept" value="1" required>
				<span>Li e aceito os <a href="/termos" target="_blank" rel="noopener">Termos de Uso</a> e a <a href="/privacidade" target="_blank" rel="noopener">Política de Privacidade</a>.</span>
			</label>
		<?php endif; ?>
		<button class="btn" type="submit">Salvar e continuar</button>
	</form>
</section>
