<?php
/** @var string $modality @var string $modalityName @var ?array $t @var array $vals @var array $items @var array $errors
 *  @var array $disciplines @var string $discipline @var bool $isNew */
use Correon\Workout;
$action = $isNew ? '/treinador/modelos/novo' : '/treinador/modelos/' . (int) $t['id'];
$err = static fn (string $k) => !empty($errors[$k]) ? '<span class="field__err">' . e($errors[$k]) . '</span>' : '';
$archived = !$isNew && $t['archived_at'];
?>
<p class="eyebrow"><a href="/treinador/modelos">← Modelos de treino</a></p>
<h1><?= $isNew ? 'Novo modelo' : e($t['name']) ?></h1>
<p>
	<span class="badge"><?= e($modalityName) ?></span>
	<?php if ($archived): ?><span class="badge badge--off">Arquivado</span><?php endif; ?>
</p>
<?php if (!empty($errors)): ?><p class="notice notice--err" role="alert">Corrija os campos destacados e tente novamente.</p><?php endif; ?>

<form class="tile form form--wide" method="post" action="<?= e($action) ?>" novalidate>
	<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
	<?php if ($isNew): ?><input type="hidden" name="modality" value="<?= e($modality) ?>"><?php endif; ?>

	<div class="row">
		<label class="field">Nome do modelo (ex.: Treino 1)
			<input type="text" name="name" value="<?= e($vals['name']) ?>" maxlength="120" required>
			<?= $err('name') ?>
		</label>
		<?php if (count($disciplines) > 1 && $isNew): ?>
			<label class="field">Disciplina
				<select name="discipline">
					<?php foreach ($disciplines as $d): ?><option value="<?= e($d) ?>"<?= $d === $discipline ? ' selected' : '' ?>><?= e(Workout::DISCIPLINES[$d]) ?></option><?php endforeach; ?>
				</select>
				<?= $err('discipline') ?>
			</label>
		<?php else: ?>
			<p class="field-static"><span class="muted">Disciplina</span><br><?= e(Workout::DISCIPLINES[$discipline]) ?></p>
		<?php endif; ?>
	</div>
	<label class="field">Orientações gerais (opcional)
		<textarea name="notes" rows="3" maxlength="1000"><?= e($vals['notes']) ?></textarea>
		<?= $err('notes') ?>
	</label>

	<?php require BASE_PATH . '/views/partials/items_form.php'; ?>

	<div class="actions">
		<button class="btn" type="submit" name="action" value="save"><?= $isNew ? 'Salvar modelo' : 'Salvar alterações' ?></button>
		<?php if (!$isNew): ?>
			<?php if ($archived): ?>
				<button class="btn btn--ghost" type="submit" name="action" value="unarchive">Reativar modelo</button>
			<?php else: ?>
				<a class="btn btn--ghost" href="/treinador/modelos/aplicar?modalidade=<?= e($modality) ?>">Aplicar a atletas</a>
				<button class="btn btn--danger" type="submit" name="action" value="archive" data-confirm="Arquivar este modelo? Ele deixa de aparecer ao aplicar treinos; os treinos já aplicados não mudam.">Arquivar</button>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php if (!$isNew): ?><p class="muted">Alterar o modelo não muda os treinos que você já aplicou a atletas.</p><?php endif; ?>
</form>
<script src="/assets/workout-form.js" defer></script>
