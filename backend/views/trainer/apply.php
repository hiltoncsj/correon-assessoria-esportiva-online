<?php /** @var list<string> $mods @var string $mod @var list<array<string,mixed>> $templates @var list<array<string,mixed>> $athletes */
$modNames = ['corrida' => 'Corrida', 'triathlon' => 'Triathlon', 'musculacao' => 'Musculação'];
?>
<p class="eyebrow">Treinador</p>
<h1>Aplicar modelos</h1>
<p class="muted">Escolha, para cada atleta, qual treino ele deve receber. Cada atleta recebe uma cópia que você pode editar depois, individualmente.</p>

<?php if (count($mods) > 1): ?>
	<nav class="monthnav" aria-label="Modalidade">
		<?php foreach ($mods as $m): ?>
			<a class="btn btn--sm <?= $m === $mod ? '' : 'btn--ghost' ?>" href="/treinador/modelos/aplicar?modalidade=<?= e($m) ?>"<?= $m === $mod ? ' aria-current="page"' : '' ?>><?= e($modNames[$m] ?? $m) ?></a>
		<?php endforeach; ?>
	</nav>
<?php endif; ?>

<?php if (!$mods): ?>
	<div class="tile"><p class="muted">Você ainda não tem atletas vinculados.</p></div>
<?php elseif (!$templates): ?>
	<div class="tile">
		<p class="muted">Você ainda não tem modelos de <?= e($modNames[$mod] ?? $mod) ?> com pelo menos uma linha.</p>
		<p><a class="btn btn--sm" href="/treinador/modelos/novo?modalidade=<?= e($mod) ?>">Criar modelo</a></p>
	</div>
<?php elseif (!$athletes): ?>
	<div class="tile"><p class="muted">Nenhum atleta vinculado a você nesta modalidade.</p></div>
<?php else: ?>
	<form class="tile form form--wide" method="post" action="/treinador/modelos/aplicar" novalidate>
		<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
		<input type="hidden" name="modality" value="<?= e($mod) ?>">
		<div class="row">
			<label class="field">Data do treino
				<input type="date" name="scheduled_date" value="<?= e(date('Y-m-d')) ?>" required>
			</label>
			<label class="check">
				<input type="checkbox" name="publish" value="1" checked>
				<span>Publicar já para os atletas (desmarque para salvar como rascunho)</span>
			</label>
		</div>

		<div class="tbl-wrap">
			<table class="tbl">
				<thead><tr><th>Atleta</th><th>Treino</th></tr></thead>
				<tbody>
				<?php foreach ($athletes as $a): ?>
					<tr>
						<td><label for="pick-<?= (int) $a['id'] ?>"><?= e($a['name']) ?></label></td>
						<td>
							<select id="pick-<?= (int) $a['id'] ?>" name="pick[<?= (int) $a['id'] ?>]" class="select">
								<option value="">— não aplicar —</option>
								<?php foreach ($templates as $t): ?>
									<option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<div class="actions">
			<button class="btn" type="submit">Aplicar treinos</button>
			<a class="btn btn--ghost" href="/treinador/modelos">Gerenciar modelos</a>
		</div>
	</form>
<?php endif; ?>
