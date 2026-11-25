<?php /** @var bool $granted */ ?>
<p class="eyebrow">Atleta</p>
<h1>Privacidade</h1>
<form class="tile form form--narrow" method="post" action="/app/privacidade">
	<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
	<h2>Divulgação no site</h2>
	<label class="check">
		<input type="checkbox" name="publicacao" value="1"<?= $granted ? ' checked' : '' ?>>
		<span>Autorizo a CorreON a publicar meu nome, meus resultados e minhas fotos no site.</span>
	</label>
	<p class="muted">É opcional e não muda nada no seu treino. Você pode retirar quando quiser: as publicações sobre você saem do site.</p>
	<div class="actions"><button class="btn btn--sm" type="submit">Salvar</button></div>
</form>
<p class="muted">Veja também a <a href="/privacidade">Política de Privacidade</a> (em elaboração).</p>
