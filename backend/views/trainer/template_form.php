<?php
/** @var string $modality @var string $modalityName @var ?array $t @var array $vals @var array $sessions @var array $errors
 *  @var array $disciplines @var bool $isNew */
use Correon\Workout;
$action = $isNew ? '/treinador/modelos/novo' : '/treinador/modelos/' . (int) $t['id'];
$err = static fn (string $k) => !empty($errors[$k]) ? '<span class="field__err">' . e($errors[$k]) . '</span>' : '';
$archived = !$isNew && $t['archived_at'];
$list = array_values($sessions);
$extra = $list ? 1 : 3;
for ($k = 0; $k < $extra; $k++) { $list[] = ['title' => '', 'discipline' => $disciplines[0], 'notes' => '', 'items' => []]; }
?>
<p class="eyebrow"><a href="/treinador/modelos">← Modelos de treino</a></p>
<h1><?= $isNew ? 'Novo modelo semanal' : e($t['name']) ?></h1>
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
		<label class="field">Nível do atleta (opcional)
			<input type="text" name="level_description" value="<?= e($vals['level_description']) ?>" maxlength="120" placeholder="ex.: pace acima de 6:30/km">
			<?= $err('level_description') ?>
		</label>
	</div>
	<p class="muted">Um modelo reúne as <strong>sessões</strong> de um nível (ex.: 3 de velocidade e 1 longo). As sessões não têm dia fixo: você escolhe o dia de cada uma na hora de enviar para cada atleta. As zonas de FC (Z1 a Z5) são convertidas em bpm para cada atleta, conforme as zonas dele.</p>
	<?php if (!empty($errors['sessions'])): ?><p class="field__err"><?= e($errors['sessions']) ?></p><?php endif; ?>

	<div data-sessions>
		<?php foreach ($list as $si => $s): ?>
			<fieldset class="session" data-session>
				<legend data-session-title>Sessão <?= $si + 1 ?></legend>
				<div class="row">
					<label class="field">Título da sessão
						<input type="text" name="sessions[<?= $si ?>][title]" value="<?= e((string) ($s['title'] ?? '')) ?>" maxlength="120" placeholder="ex.: Velocidade 1 (vazio = Sessão N)">
					</label>
					<?php if (count($disciplines) > 1): ?>
						<label class="field">Disciplina
							<select name="sessions[<?= $si ?>][discipline]">
								<?php foreach ($disciplines as $d): ?><option value="<?= e($d) ?>"<?= ($s['discipline'] ?? '') === $d ? ' selected' : '' ?>><?= e(Workout::DISCIPLINES[$d]) ?></option><?php endforeach; ?>
							</select>
						</label>
					<?php endif; ?>
				</div>
				<label class="field">Orientações da sessão (opcional)
					<textarea name="sessions[<?= $si ?>][notes]" rows="2" maxlength="1000"><?= e((string) ($s['notes'] ?? '')) ?></textarea>
				</label>
				<?php
				$items = $s['items'] ?? [];
				$discipline = in_array($s['discipline'] ?? '', $disciplines, true) ? $s['discipline'] : $disciplines[0];
				$prefix = "sessions[$si][items]";
				$isNewBlock = !$items;
				$errorsBlock = [];
				$savedErrors = $errors; $savedIsNew = $isNew; $errors = $errorsBlock; $isNew = $isNewBlock;
				require BASE_PATH . '/views/partials/items_form.php';
				$errors = $savedErrors; $isNew = $savedIsNew;
				?>
			</fieldset>
		<?php endforeach; ?>
		<p><button class="btn btn--sm btn--ghost" type="button" data-add-session hidden>+ Adicionar sessão</button></p>
	</div>

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
	<input type="hidden" name="_end" value="1">
</form>
<script src="/assets/workout-form.js" defer></script>
