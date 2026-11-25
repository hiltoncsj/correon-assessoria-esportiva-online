<?php
/** @var array $athlete @var string $modality @var string $modalityName @var ?array $w @var array $vals @var array $items
 *  @var array $errors @var array $disciplines @var string $discipline @var ?array $log @var array $versions @var bool $editable */
use Correon\Workout;
$isNew = $w === null;
$strength = Workout::isStrength($discipline);
$action = $isNew ? '/treinador/treinos/novo' : '/treinador/treinos/' . (int) $w['id'];
$err = static fn (string $k) => !empty($errors[$k]) ? '<span class="field__err">' . e($errors[$k]) . '</span>' : '';
$statusClass = ['draft' => 'badge--draft', 'published' => 'badge--ok', 'cancelled' => 'badge--off'];
?>
<p class="eyebrow"><a href="/treinador/atletas/<?= (int) $athlete['id'] ?>">← <?= e($athlete['name']) ?></a></p>
<h1><?= $isNew ? 'Novo treino' : e($w['title']) ?></h1>
<p>
	<span class="badge"><?= e($modalityName) ?></span>
	<?php if (!$isNew): ?><span class="badge <?= e($statusClass[$w['status']]) ?>"><?= e(Workout::STATUS[$w['status']]) ?></span>
	<span class="muted">Versão <?= (int) $w['version'] ?></span><?php endif; ?>
	<?php if (!empty($w['template_name'])): ?><span class="muted">· criado a partir do modelo “<?= e($w['template_name']) ?>”</span><?php endif; ?>
</p>

<?php if (!empty($errors)): ?><p class="notice notice--err" role="alert">Corrija os campos destacados e tente novamente.</p><?php endif; ?>

<?php if (!$editable): ?>
	<div class="tile">
		<p class="muted"><?= $w['status'] === 'cancelled'
			? 'Este treino foi cancelado e não pode mais ser alterado.'
			: 'O atleta já registrou este treino. Para preservar o histórico, ele não pode mais ser alterado.' ?></p>
		<dl class="facts">
			<dt>Data</dt><dd><?= e(\Correon\View::dateBr($w['scheduled_date'])) ?></dd>
			<dt>Disciplina</dt><dd><?= e(Workout::DISCIPLINES[$w['discipline']]) ?></dd>
			<?php if ($w['notes']): ?><dt>Orientações</dt><dd><?= e($w['notes']) ?></dd><?php endif; ?>
		</dl>
		<?php require BASE_PATH . '/views/partials/items.php'; ?>
	</div>
<?php else: ?>
<form class="tile form form--wide" method="post" action="<?= e($action) ?>" novalidate>
	<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
	<?php if ($isNew): ?>
		<input type="hidden" name="athlete_id" value="<?= (int) $athlete['id'] ?>">
		<input type="hidden" name="modality" value="<?= e($modality) ?>">
	<?php else: ?>
		<input type="hidden" name="version" value="<?= (int) $w['version'] ?>">
	<?php endif; ?>

	<div class="row">
		<label class="field">Título
			<input type="text" name="title" value="<?= e($vals['title']) ?>" maxlength="120" required>
			<?= $err('title') ?>
		</label>
		<label class="field">Data
			<input type="date" name="scheduled_date" value="<?= e($vals['scheduled_date']) ?>" required>
			<?= $err('scheduled_date') ?>
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
		<?php if ($isNew): ?>
			<button class="btn btn--ghost" type="submit" name="action" value="draft">Salvar rascunho</button>
			<button class="btn" type="submit" name="action" value="publish">Publicar para o atleta</button>
		<?php else: ?>
			<button class="btn btn--ghost" type="submit" name="action" value="save">Salvar</button>
			<?php if ($w['status'] === 'draft'): ?>
				<button class="btn" type="submit" name="action" value="publish">Publicar para o atleta</button>
			<?php else: ?>
				<button class="btn btn--ghost" type="submit" name="action" value="unpublish">Voltar a rascunho</button>
			<?php endif; ?>
			<button class="btn btn--danger" type="submit" name="action" value="cancel" data-confirm="Cancelar este treino? Esta ação não pode ser desfeita.">Cancelar treino</button>
		<?php endif; ?>
	</div>
	<p class="muted">“Salvar” em um treino já publicado atualiza o que o atleta vê e gera uma nova versão no histórico.</p>
</form>
<script src="/assets/workout-form.js" defer></script>
<?php endif; ?>

<?php if ($log): ?>
	<section class="tile" aria-labelledby="reg">
		<h2 id="reg">Registro do atleta</h2>
		<?php require BASE_PATH . '/views/partials/log.php'; ?>
	</section>
<?php endif; ?>

<?php if ($versions): ?>
	<section class="tile" aria-labelledby="hist">
		<h2 id="hist">Histórico de versões</h2>
		<ul class="history">
			<?php foreach ($versions as $v): ?>
				<li>v<?= (int) $v['version'] ?> · <?= e(Workout::noteLabel($v['change_note'])) ?> por <?= e($v['author']) ?> em <?= e(date('d/m/Y H:i', strtotime($v['created_at']))) ?></li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>
