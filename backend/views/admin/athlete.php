<?php /** @var array $person @var array $profile @var array $links @var array $trainers @var array $modalities @var array $summary */ ?>
<p class="eyebrow"><a href="/admin/atletas">← Atletas</a></p>
<h1><?= e($person['name']) ?></h1>

<section class="tile" aria-labelledby="h-vinc">
	<h2 id="h-vinc">Treinadores</h2>
	<?php if (!$links): ?><p class="muted">Nenhum treinador atribuído.</p><?php else: ?>
		<ul class="done-list">
			<?php foreach ($links as $l): ?>
				<li>
					<strong><?= e($l['modality']) ?></strong>
					<a href="/admin/treinadores/<?= (int) $l['trainer_id'] ?>"><?= e($l['trainer']) ?></a>
					<?php if ($l['status'] === 'active'): ?>
						<span class="badge badge--ok">Ativo</span>
						<form method="post" action="/admin/atletas/<?= (int) $person['id'] ?>/vinculos/encerrar">
							<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
							<input type="hidden" name="trainer_id" value="<?= (int) $l['trainer_id'] ?>">
							<input type="hidden" name="modality" value="<?= e($l['slug']) ?>">
							<button class="btn btn--sm btn--danger" type="submit" data-confirm="Encerrar este vínculo? O treinador perde o acesso ao atleta nesta modalidade; o histórico é mantido.">Encerrar</button>
						</form>
					<?php else: ?>
						<span class="muted">encerrado em <?= e(date('d/m/Y', strtotime($l['ended_at']))) ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<h3>Atribuir treinador</h3>
	<?php if (!$trainers): ?><p class="muted">Cadastre um treinador primeiro. <a href="/admin/treinadores">Cadastrar</a></p><?php else: ?>
		<form class="form" method="post" action="/admin/atletas/<?= (int) $person['id'] ?>/vinculos" novalidate>
			<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
			<div class="row">
				<label class="field">Treinador
					<select name="trainer_id">
						<option value="">—</option>
						<?php foreach ($trainers as $t): ?>
							<option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?> (<?= (int) $t['capacity']['active'] ?>/<?= (int) $t['capacity']['limit'] ?><?= $t['capacity']['state'] === 'full' ? ', esgotado' : '' ?>)</option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="field">Modalidade
					<select name="modality">
						<option value="">—</option>
						<?php foreach ($modalities as $slug => $name): ?><option value="<?= e($slug) ?>"><?= e($name) ?></option><?php endforeach; ?>
					</select>
				</label>
			</div>
			<label class="field">Motivo da exceção (só se o treinador estiver no limite)
				<input type="text" name="reason" maxlength="300">
			</label>
			<div class="actions"><button class="btn btn--sm" type="submit">Atribuir</button></div>
		</form>
	<?php endif; ?>
</section>

<div class="grid">
	<section class="tile" aria-labelledby="h-perfil">
		<h2 id="h-perfil">Perfil</h2>
		<dl class="facts">
			<dt>Telefone</dt><dd><?= e($profile['phone'] ?? '') ?: '—' ?></dd>
			<dt>Cidade</dt><dd><?= e(trim(($profile['city'] ?? '') . '/' . ($profile['state'] ?? ''), '/')) ?: '—' ?></dd>
			<dt>Objetivo</dt><dd><?= e($profile['goal'] ?? '') ?: '—' ?></dd>
			<dt>Emergência</dt><dd><?= e(trim(($profile['emergency_name'] ?? '') . ' · ' . ($profile['emergency_phone'] ?? ''), ' ·')) ?: '—' ?></dd>
		</dl>
	</section>
	<section class="tile" aria-labelledby="h-res">
		<h2 id="h-res">Últimas 4 semanas</h2>
		<dl class="facts">
			<dt>Treinos publicados</dt><dd><?= (int) $summary['planned'] ?></dd>
			<dt>Realizados</dt><dd><?= (int) $summary['done'] ?></dd>
		</dl>
	</section>
</div>

<?php require BASE_PATH . '/views/partials/account.php'; ?>
