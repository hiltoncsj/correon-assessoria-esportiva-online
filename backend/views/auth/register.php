<?php /** @var array $old @var array $errors */ ?>
<section class="card">
	<h1>Criar conta</h1>
	<?php if (!empty($errors['form'])): ?><p class="notice notice--err" role="alert"><?= e($errors['form']) ?></p><?php endif; ?>
	<form method="post" action="/cadastro" novalidate>
		<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
		<label class="field">Nome completo
			<input type="text" name="name" value="<?= e($old['name']) ?>" autocomplete="name" maxlength="120" required autofocus>
			<?php if (!empty($errors['name'])): ?><span class="field__err"><?= e($errors['name']) ?></span><?php endif; ?>
		</label>
		<label class="field">E-mail
			<input type="email" name="email" value="<?= e($old['email']) ?>" autocomplete="email" maxlength="190" required>
			<?php if (!empty($errors['email'])): ?><span class="field__err"><?= e($errors['email']) ?></span><?php endif; ?>
		</label>
		<label class="field">Senha (mínimo 10 caracteres)
			<input type="password" name="password" autocomplete="new-password" minlength="10" required>
			<?php if (!empty($errors['password'])): ?><span class="field__err"><?= e($errors['password']) ?></span><?php endif; ?>
		</label>
		<label class="field">Repita a senha
			<input type="password" name="password2" autocomplete="new-password" required>
			<?php if (!empty($errors['password2'])): ?><span class="field__err"><?= e($errors['password2']) ?></span><?php endif; ?>
		</label>
		<label class="check">
			<input type="checkbox" name="accept" value="1" required>
			<span>Li e aceito os <a href="/termos" target="_blank" rel="noopener">Termos de Uso</a> e a <a href="/privacidade" target="_blank" rel="noopener">Política de Privacidade</a>.</span>
		</label>
		<?php if (!empty($errors['accept'])): ?><span class="field__err"><?= e($errors['accept']) ?></span><?php endif; ?>
		<button class="btn" type="submit">Criar conta</button>
	</form>
	<p class="links">Já tem conta? <a href="/entrar">Entrar</a></p>
	<p class="links"><a href="<?= e(\Correon\View::site()) ?>">← Voltar para o site</a></p>
</section>
