<?php
/** Passo 2: semana de cada atleta.
 *  @var string $mod @var string $monday @var array<int,array<string,mixed>> $library @var list<array<string,mixed>> $athletes @var string $back */
use Correon\Templates;
$dayDate = static fn (int $d) => date('d/m', strtotime($monday . ' +' . ($d - 1) . ' days'));
?>
<p class="eyebrow"><a href="<?= e($back) ?>">← Voltar ao passo 1</a></p>
<h1>Montar a semana</h1>
<p class="muted">Passo 2: semana de <?= e(date('d/m', strtotime($monday))) ?> a <?= e($dayDate(7)) ?>. Em cada dia, escolha a sessão que o atleta vai fazer, na ordem que quiser. Dias em “—” ficam sem treino. Os dias já cadastrados do atleta vêm preenchidos.</p>

<form class="form form--wide" method="post" action="/treinador/modelos/aplicar" novalidate>
	<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
	<input type="hidden" name="modality" value="<?= e($mod) ?>">
	<input type="hidden" name="scheduled_date" value="<?= e($monday) ?>">

	<?php foreach ($athletes as $a): ?>
		<fieldset class="plan-athlete">
			<legend><?= e($a['name']) ?> · <?= e($library[$a['template']]['name']) ?></legend>
			<?php if (!$a['zones']): ?><p><a href="/treinador/zonas" class="badge badge--off">sem zonas de FC</a></p><?php endif; ?>
			<?php if (!$a['days']): ?>
				<p class="muted">Este atleta ainda não tem dias cadastrados: escolha os dias abaixo (eles serão lembrados).</p>
			<?php elseif ($a['unplaced'] > 0): ?>
				<p class="muted">Há <?= (int) $a['unplaced'] ?> sessão(ões) do modelo sem dia: o atleta tem menos dias que sessões. Ajuste se quiser.</p>
			<?php endif; ?>
			<div class="week">
				<?php foreach (Templates::WEEKDAYS as $day => $label): $chosen = $a['grid'][$day] ?? 0; ?>
					<label class="field"><?= e($label) ?> <?= e($dayDate($day)) ?>
						<select name="plan[<?= (int) $a['id'] ?>][<?= $day ?>]" class="select">
							<option value="">—</option>
							<?php
							// o modelo do atleta vem primeiro; os demais ficam disponíveis para casos especiais
							$groups = [$a['template'] => $library[$a['template']]] + $library;
							foreach ($groups as $tpl): ?>
								<optgroup label="<?= e($tpl['name']) ?>">
									<?php foreach ($tpl['sessions'] as $k => $s): ?>
										<option value="<?= (int) $s['id'] ?>"<?= $chosen === (int) $s['id'] ? ' selected' : '' ?>><?= e($tpl['name']) ?> · <?= ($k + 1) ?>. <?= e($s['title']) ?></option>
									<?php endforeach; ?>
								</optgroup>
							<?php endforeach; ?>
						</select>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>
	<?php endforeach; ?>

	<label class="check">
		<input type="checkbox" name="publish" value="1" checked>
		<span>Publicar já para os atletas (desmarque para salvar como rascunho)</span>
	</label>
	<div class="actions">
		<button class="btn" type="submit">Enviar treinos</button>
		<a class="btn btn--ghost" href="<?= e($back) ?>">Voltar</a>
	</div>
</form>
