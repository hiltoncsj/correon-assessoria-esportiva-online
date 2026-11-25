<?php /** @var array $person @var array $trainers @var array<string,string> $old @var ?string $error */
use Correon\Personal;
?>
<p class="eyebrow"><a href="/admin/atletas/<?= (int) $person['id'] ?>">← <?= e($person['name']) ?></a></p>
<h1>Novo Personal Trainer</h1>
<form class="tile form" method="post" action="/admin/atletas/<?= (int) $person['id'] ?>/personal" novalidate>
	<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
	<?php if ($error): ?><p class="notice notice--err" role="alert"><?= e($error) ?></p><?php endif; ?>
	<div class="row">
		<label class="field">Formato<select name="format"><?php foreach (Personal::FORMATS as $k => $v): ?><option value="<?= e($k) ?>"<?= $old['format'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
		<label class="field">Treinador<select name="trainer_id"><option value="">—</option><?php foreach ($trainers as $t): ?><option value="<?= (int) $t['id'] ?>"<?= $old['trainer_id'] === (string) $t['id'] ? ' selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></label>
	</div>
	<div class="row">
		<label class="field">Nº de sessões (pacote)<input type="text" inputmode="numeric" name="sessions_total" value="<?= e($old['sessions_total']) ?>" maxlength="3"></label>
		<label class="field">Vezes por semana (mensalidade)<input type="text" inputmode="numeric" name="weekly_frequency" value="<?= e($old['weekly_frequency']) ?>" maxlength="1"></label>
		<label class="field">Duração da sessão (min)<input type="text" inputmode="numeric" name="session_minutes" value="<?= e($old['session_minutes']) ?>" maxlength="3"></label>
	</div>
	<div class="row">
		<label class="field">Valor (opcional)<input type="text" inputmode="decimal" name="price" value="<?= e($old['price']) ?>" maxlength="10" placeholder="ex.: 500,00"></label>
		<label class="field">Local (opcional)<input type="text" name="location" value="<?= e($old['location']) ?>" maxlength="200"></label>
	</div>
	<div class="row">
		<label class="field">Início<input type="date" name="starts_on" value="<?= e($old['starts_on']) ?>"></label>
		<label class="field">Válido até (obrigatório na mensalidade)<input type="date" name="valid_until" value="<?= e($old['valid_until']) ?>"></label>
	</div>
	<label class="field">Motivo da exceção (só se o treinador estiver no limite presencial)<input type="text" name="reason" value="<?= e($old['reason']) ?>" maxlength="280"></label>
	<div class="actions"><button class="btn" type="submit">Cadastrar Personal</button></div>
</form>
