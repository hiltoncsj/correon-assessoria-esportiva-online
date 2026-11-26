<?php /** @var array{next:array,past:array} $goals */ ?>
<p class="eyebrow">Atleta</p>
<h1>Objetivos e provas</h1>
<p class="muted">Coloque o seu nome nos objetivos em que vai participar. Seus treinadores e a administração veem o seu nome, a meta e o resultado; outros atletas veem apenas o número de inscritos.</p>

<?php foreach (['next' => 'Próximos', 'past' => 'Anteriores'] as $key => $heading): ?>
	<?php if ($key === 'past' && !$goals[$key]) { continue; } ?>
	<section class="tile" aria-labelledby="h-<?= $key ?>">
		<h2 id="h-<?= $key ?>"><?= $heading ?></h2>
		<?php if (!$goals[$key]): ?><p class="muted">Nada cadastrado ainda. Os treinadores cadastram os objetivos e provas.</p><?php else: ?>
			<ul class="done-list">
				<?php foreach ($goals[$key] as $g): $url = '/app/objetivos/' . (int) $g['id']; ?>
					<li>
						<?php require __DIR__ . '/summary.php'; ?>
						<span class="muted"><?= (int) $g['total'] ?> inscrito(s)</span>
						<?php if ((int) $g['joined']): ?>
							<span class="badge badge--ok">Meu nome está aqui</span>
							<form class="form form--inline" method="post" action="<?= e($url) ?>/meu">
								<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
								<label class="field">Minha meta<input type="text" name="target" value="<?= e((string) $g['target']) ?>" maxlength="150"></label>
								<label class="field">Meu resultado<input type="text" name="result" value="<?= e((string) $g['result']) ?>" maxlength="500"></label>
								<button class="btn btn--sm btn--ghost" type="submit">Salvar</button>
							</form>
							<form method="post" action="<?= e($url) ?>/sair">
								<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
								<button class="btn btn--sm btn--danger" type="submit">Remover meu nome</button>
							</form>
						<?php else: ?>
							<form method="post" action="<?= e($url) ?>/entrar">
								<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
								<button class="btn btn--sm" type="submit">Adicionar meu nome</button>
							</form>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
<?php endforeach; ?>
