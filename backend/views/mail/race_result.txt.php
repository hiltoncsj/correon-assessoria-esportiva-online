<?php /** Versão em texto (sem HTML) do lembrete. */
echo "Parabéns, $first!\n\n";
echo "A prova $race ($date" . ($distance !== '' ? " · $distance" : '') . ") já passou. Queremos saber como foi.\n\n";
echo $filled
    ? "O seu resultado já está registrado. Vale conferir se os dados estão completos e atualizados.\n\n"
    : "Registre o seu resultado enquanto os detalhes ainda estão frescos: o tempo, a sensação, o que funcionou e o que dá para melhorar.\n\n";
echo "Com os dados em dia, você acompanha a sua evolução e a equipe planeja melhor os próximos treinos.\n\n";
echo "Registrar meu resultado: $url\n\n";
echo "Não participou? Entre no site e retire o seu nome da prova.\n\nCorreON\n";
