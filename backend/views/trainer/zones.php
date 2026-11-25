<?php /** @var list<array<string,mixed>> $athletes @var array<int,string> $errors */
use Correon\Workout;
?>
<p class="eyebrow">Treinador</p>
<h1>Zonas de FC</h1>
<p class="muted">Frequência cardíaca de cada atleta, por zona (em cima o mínimo, embaixo o máximo, em bpm). Cada atleta vê nos treinos apenas os valores dele. Para preencher pelo padrão, informe a FC máxima e clique em “Calcular” na linha do atleta. Padrão (% da FCmáx):
	<?php foreach (Workout::DEFAULT_ZONE_PCT as $z => [$lo, $hi]): ?>Z<?= $z ?> <?= $lo ?>–<?= $hi ?>%<?= $z < Workout::ZONES ? ' · ' : '.' ?><?php endforeach; ?></p>

<?php if (!$athletes): ?>
	<div class="tile"><p class="muted">Você ainda não tem atletas vinculados.</p></div>
<?php else: ?>
	<?php if ($errors): ?><p class="notice notice--err" role="alert">Nada foi salvo. Corrija as linhas indicadas.</p><?php endif; ?>
	<form class="form form--wide" method="post" action="/treinador/zonas" novalidate>
		<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
		<!-- botão padrão do formulário: Enter salva (não dispara "Calcular") -->
		<button type="submit" class="visually-hidden" tabindex="-1" aria-hidden="true">Salvar</button>
		<div class="tbl-wrap">
			<table class="tbl ztbl">
				<thead>
					<tr>
						<th>Atleta</th><th>FC máx</th>
						<?php for ($z = 1; $z <= Workout::ZONES; $z++): ?><th>Zona <?= $z ?></th><?php endfor; ?>
						<th></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($athletes as $a): $id = (int) $a['id']; ?>
					<tr>
						<td>
							<a href="/treinador/atletas/<?= $id ?>"><?= e($a['name']) ?></a><br>
							<span class="muted"><?= e($a['modalities']) ?></span>
							<?php if (!empty($errors[$id])): ?><br><span class="field__err"><?= e($errors[$id]) ?></span><?php endif; ?>
						</td>
						<td><input type="text" inputmode="numeric" name="z[<?= $id ?>][max]" value="<?= e($a['max']) ?>" maxlength="3" aria-label="FC máxima de <?= e($a['name']) ?>"></td>
						<?php for ($z = 1; $z <= Workout::ZONES; $z++): ?>
							<td>
								<input type="text" inputmode="numeric" name="z[<?= $id ?>][zones][<?= $z ?>][min]" value="<?= e($a['zones'][$z]['min']) ?>" maxlength="3" placeholder="mín" aria-label="<?= e($a['name']) ?>, zona <?= $z ?>, mínimo">
								<input type="text" inputmode="numeric" name="z[<?= $id ?>][zones][<?= $z ?>][max]" value="<?= e($a['zones'][$z]['max']) ?>" maxlength="3" placeholder="máx" aria-label="<?= e($a['name']) ?>, zona <?= $z ?>, máximo">
							</td>
						<?php endfor; ?>
						<td><button class="btn btn--sm btn--ghost" type="submit" name="calc" value="<?= $id ?>">Calcular</button></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<div class="actions"><button class="btn" type="submit">Salvar zonas</button></div>
		<input type="hidden" name="_end" value="1">
	</form>
<?php endif; ?>
