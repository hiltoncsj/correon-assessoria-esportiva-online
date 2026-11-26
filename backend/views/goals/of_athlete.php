<?php /** @var array $athlete @var list<array<string,mixed>> $items */ ?>
<p class="eyebrow"><a href="/treinador/atletas/<?= (int) $athlete['id'] ?>">← <?= e($athlete['name']) ?></a></p>
<h1>Objetivos e provas</h1>
<section class="tile"><?php require __DIR__ . '/items.php'; ?></section>
