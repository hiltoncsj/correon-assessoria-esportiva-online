<?php
/** Passo 2: semana de cada atleta.
 *  @var string $mod @var string $monday @var array<int,array<string,mixed>> $library @var list<array<string,mixed>> $athletes @var string $back */
use Correon\Templates;
$dayDate = static fn (int $d) => date('d/m', strtotime($monday . ' +' . ($d - 1) . ' days'));
?>
<p class="eyebrow"><a href="<?= e($back) ?>">← Voltar ao passo 1</a></p>
<h1>Montar a semana</h1>
<p class="muted">Passo 2: semana de <?= e(date('d/m', strtotime($monday))) ?> a <?= e($dayDate(7)) ?>. Em cada dia, escolha a sessão que o atleta vai fazer; use “+ outro treino” para pôr dois ou mais no mesmo dia (podem se repetir). Para misturar modalidades, o atleta precisa estar vinculado a você em cada uma. Dias em “—” ficam sem treino. Os dias já cadastrados do atleta vêm preenchidos.</p>

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
				<?php foreach (Templates::WEEKDAYS as $day => $label):
					$chosen = $a['grid'][$day] ?? 0;
					$name = 'plan[' . (int) $a['id'] . '][' . $day . '][]';
					$options = static function (int $selected) use ($a): string {
						$html = '<option value="">—</option>';
						foreach ($a['allowed'] as $tpl) {
							$html .= '<optgroup label="' . e($tpl['modality_name'] . ' · ' . $tpl['name']) . '">';
							foreach ($tpl['sessions'] as $k => $s) {
								$html .= '<option value="' . (int) $s['id'] . '"' . ($selected === (int) $s['id'] ? ' selected' : '') . '>' . e($tpl['name'] . ' · ' . ($k + 1) . '. ' . $s['title']) . '</option>';
							}
							$html .= '</optgroup>';
						}
						return $html;
					}; ?>
					<fieldset class="day" data-day>
						<legend><?= e($label) ?> <?= e($dayDate($day)) ?></legend>
						<select name="<?= e($name) ?>" class="select" data-slot aria-label="<?= e($label) ?>, treino de <?= e($a['name']) ?>"><?= $options($chosen) ?></select>
						<noscript><select name="<?= e($name) ?>" class="select" aria-label="<?= e($label) ?>, segundo treino"><?= $options(0) ?></select></noscript>
						<button type="button" class="btn btn--sm btn--ghost" data-add-slot hidden>+ outro treino</button>
					</fieldset>
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
	<input type="hidden" name="_end" value="1">
</form>
<script src="/assets/workout-form.js" defer></script>
