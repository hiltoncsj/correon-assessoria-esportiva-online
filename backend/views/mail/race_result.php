<?php /** @var string $first @var string $race @var string $date @var string $distance @var bool $filled @var string $url */ ?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Como foi a prova?</title></head>
<body style="margin:0;padding:0;background:#000000;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#000000;">
<tr><td align="center" style="padding:24px 12px;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;font-family:Arial,Helvetica,sans-serif;">
		<tr><td align="center" style="background:#000000;padding:16px 24px 24px;">
			<img src="cid:logo-correon" alt="CorreON" width="200" style="display:block;width:200px;max-width:70%;height:auto;border:0;">
		</td></tr>
		<tr><td style="background:#ffffff;border-radius:12px;border-top:6px solid #01cd52;padding:32px 24px;color:#000000;font-size:16px;line-height:1.6;">
			<p style="margin:0 0 16px;font-size:22px;font-weight:bold;">Parabéns, <?= e($first) ?>!</p>
			<p style="margin:0 0 16px;">A prova <strong><?= e($race) ?></strong> (<?= e($date) ?><?= $distance !== '' ? ' · ' . e($distance) : '' ?>) já passou. Queremos saber como foi.</p>
			<?php if ($filled): ?>
				<p style="margin:0 0 16px;">O seu resultado já está registrado. Vale conferir se os dados estão completos e atualizados.</p>
			<?php else: ?>
				<p style="margin:0 0 16px;">Registre o seu resultado enquanto os detalhes ainda estão frescos: o tempo, a sensação, o que funcionou e o que dá para melhorar.</p>
			<?php endif; ?>
			<p style="margin:0 0 24px;">Cada resultado anotado mostra de onde você saiu e até onde já chegou. Com os dados em dia, você acompanha a sua evolução e a equipe planeja melhor os próximos treinos. Toda linha de chegada conta, não só o recorde.</p>
			<table role="presentation" cellpadding="0" cellspacing="0" align="center" style="margin:0 auto 24px;"><tr>
				<td align="center" style="background:#01cd52;border-radius:999px;">
					<a href="<?= e($url) ?>" style="display:inline-block;padding:14px 28px;color:#000000;font-weight:bold;text-decoration:none;text-transform:uppercase;">Registrar meu resultado</a>
				</td>
			</tr></table>
			<p style="margin:0 0 8px;font-size:13px;color:#555555;">Se o botão não funcionar, copie o endereço: <a href="<?= e($url) ?>" style="color:#000000;"><?= e($url) ?></a></p>
			<p style="margin:0;font-size:13px;color:#555555;">Não participou? Entre no site e retire o seu nome da prova.</p>
		</td></tr>
		<tr><td align="center" style="padding:20px 24px;color:#bbbbbb;font-size:12px;line-height:1.5;">
			CONECTE-SE, CORRA, EVOLUA!<br>Você recebeu este e-mail porque o seu nome está inscrito nesta prova na área do atleta.
		</td></tr>
	</table>
</td></tr>
</table>
</body>
</html>
