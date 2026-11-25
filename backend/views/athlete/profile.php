<?php /** @var array $u @var array $old @var array $errors @var array $states */
$err = static fn (string $k) => !empty($errors[$k]) ? '<span class="field__err">' . e($errors[$k]) . '</span>' : '';
?>
<p class="eyebrow">Atleta</p>
<h1>Meu perfil</h1>

<section class="tile" aria-labelledby="h-zonas">
	<h2 id="h-zonas">Minhas zonas de frequência cardíaca</h2>
	<?php if (!$zones): ?>
		<p class="muted">Seu treinador ainda não definiu suas zonas. Elas são usadas para mostrar a faixa de bpm de cada treino.</p>
	<?php else: ?>
		<dl class="facts">
			<?php if (!empty($maxHr)): ?><dt>FC máxima</dt><dd><?= (int) $maxHr ?> bpm</dd><?php endif; foreach ($zones as $zn => $r): ?><dt>Zona <?= (int) $zn ?></dt><dd><?= (int) $r['min'] ?>–<?= (int) $r['max'] ?> bpm</dd><?php endforeach; ?>
		</dl>
		<p class="muted">Definidas pelo seu treinador.</p>
	<?php endif; ?>
</section>

<form class="tile form" method="post" action="/app/perfil" novalidate>
	<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

	<p class="muted">A administração e os treinadores vinculados a você podem ver seu telefone, idade, cidade, objetivo, histórico esportivo e contato de emergência.</p>

	<h2>Dados pessoais</h2>
	<label class="field">Nome completo
		<input type="text" name="name" value="<?= e($old['name'] ?? '') ?>" maxlength="120" autocomplete="name" required>
		<?= $err('name') ?>
	</label>
	<p class="muted">E-mail: <?= e($u['email']) ?> (para alterar o e-mail, fale com a CorreON).</p>
	<div class="row">
		<label class="field">Telefone (WhatsApp)
			<input type="tel" name="phone" value="<?= e($old['phone'] ?? '') ?>" maxlength="20" autocomplete="tel" placeholder="(11) 91234-5678">
			<?= $err('phone') ?>
		</label>
		<label class="field">Data de nascimento
			<input type="date" name="birth_date" value="<?= e($old['birth_date'] ?? '') ?>" autocomplete="bday">
			<?= $err('birth_date') ?>
		</label>
	</div>
	<div class="row">
		<label class="field">Cidade
			<input type="text" name="city" value="<?= e($old['city'] ?? '') ?>" maxlength="80" autocomplete="address-level2">
			<?= $err('city') ?>
		</label>
		<label class="field">Estado
			<select name="state" autocomplete="address-level1">
				<option value="">—</option>
				<?php foreach ($states as $s): ?><option value="<?= e($s) ?>"<?= ($old['state'] ?? '') === $s ? ' selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
			</select>
			<?= $err('state') ?>
		</label>
	</div>

	<h2>Dados esportivos</h2>
	<label class="field">Seu objetivo
		<textarea name="goal" rows="3" maxlength="500"><?= e($old['goal'] ?? '') ?></textarea>
		<?= $err('goal') ?>
	</label>
	<label class="field">Histórico esportivo
		<textarea name="sports_background" rows="4" maxlength="1000"><?= e($old['sports_background'] ?? '') ?></textarea>
		<?= $err('sports_background') ?>
	</label>
	<p class="muted">Não informe dados de saúde nestes campos.</p>

	<h2>Contato de emergência</h2>
	<div class="row">
		<label class="field">Nome
			<input type="text" name="emergency_name" value="<?= e($old['emergency_name'] ?? '') ?>" maxlength="120">
			<?= $err('emergency_name') ?>
		</label>
		<label class="field">Telefone
			<input type="tel" name="emergency_phone" value="<?= e($old['emergency_phone'] ?? '') ?>" maxlength="20">
			<?= $err('emergency_phone') ?>
		</label>
	</div>

	<button class="btn" type="submit">Salvar perfil</button>
</form>
