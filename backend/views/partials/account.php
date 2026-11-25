<?php /** @var array $person  Ações de conta (admin). */ ?>
<section class="tile" aria-labelledby="h-conta">
	<h2 id="h-conta">Conta</h2>
	<dl class="facts">
		<dt>E-mail</dt><dd><?= e($person['email']) ?></dd>
		<dt>Situação</dt><dd><?= $person['status'] === 'active' ? 'Ativa' : 'Bloqueada' ?></dd>
		<dt>Cadastro</dt><dd><?= e(date('d/m/Y', strtotime($person['created_at']))) ?></dd>
		<dt>Último acesso</dt><dd><?= $person['last_login_at'] ? e(date('d/m/Y H:i', strtotime($person['last_login_at']))) : 'Nunca' ?></dd>
	</dl>
	<div class="actions">
		<?php if ($person['status'] === 'active'): ?>
			<form method="post" action="/admin/usuarios/<?= (int) $person['id'] ?>/senha">
				<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
				<button class="btn btn--sm btn--ghost" type="submit">Gerar link de senha</button>
			</form>
		<?php endif; ?>
		<form method="post" action="/admin/usuarios/<?= (int) $person['id'] ?>/status">
			<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
			<?php if ($person['status'] === 'active'): ?>
				<button class="btn btn--sm btn--danger" type="submit" name="action" value="block" data-confirm="Bloquear esta conta? A pessoa será desconectada e não conseguirá entrar.">Bloquear conta</button>
			<?php else: ?>
				<button class="btn btn--sm" type="submit" name="action" value="unblock">Reativar conta</button>
			<?php endif; ?>
		</form>
	</div>
</section>
<script src="<?= e(\Correon\View::asset('/assets/workout-form.js')) ?>" defer></script>
