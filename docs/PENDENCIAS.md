# PENDÊNCIAS — decisões a tomar (atualizado ao fim da Sprint 8)

Será revisado e apresentado ao fim da Sprint 9. Nada abaixo foi presumido no sistema além do indicado em "Hoje".

## A. Bloqueiam a publicação (resolver antes de abrir cadastros)

| # | Pendência | Hoje |
|---|---|---|
| A1 | Textos de **Termos de Uso** e **Política de Privacidade** (com responsável LGPD) | Páginas "em elaboração"; aceite gravado como `rascunho-0` |
| A2 | Texto da autorização de divulgação de nome, resultados e fotos (inclusive **menores**) | Redação provisória |
| A3 | LocalWeb: `mail()`/SMTP entrega e-mail? `mod_rewrite`, `.user.ini`, extensão GD, PHP 8.3, cron, SSH | Não confirmado; sem e-mail a recuperação de senha não funciona |
| A4 | Backup do banco e das fotos (`storage/uploads`) e teste de restauração | Sem rotina |
| A5 | Quem é o administrador de produção; senha forte | A criar |

## B. Comercial e pagamentos

| # | Pendência | Hoje |
|---|---|---|
| B1 | **Gateway** (InfinitePay?): parcelamento, recorrência, aviso automático | Baixa manual; nada pago ativado |
| B2 | Parcelas são no cartão (total garantido) ou cobrança por mês? Há juros? | Parcelas internas; site não afirma "sem juros" |
| B3 | Combo mensal: confirmar **R$ 306** (antes R$ 200) | R$ 306 (15% em todos os períodos) |
| B4 | **Inadimplência:** dias até suspender e o que é suspenso | Só sinaliza "em atraso" |
| B5 | **Cancelamento:** multa, prazo mínimo, reembolso | Sem multa |
| B6 | Renovação de aluno novo mantém o valor antigo ou usa a tabela? | Mantém o valor anterior; renovação manual |
| B7 | Alunos antigos (13): modalidades, combos, o que acontece se cancelar/mudar de plano | Condição "R$ 120 mensal de uma modalidade" ou valor negociado |
| B8 | Troca Corrida ↔ Triathlon: livre ou limitada? | Livre, pelo admin |
| B9 | Contratação on-line pelo aluno (e destino do botão CONTRATAR) | Botão leva a `#contato`; contrato é feito pelo admin |
| B10 | Preço do site e do painel são separados: unificar? | Alterar nos dois |

## C. Personal Trainer

| # | Pendência | Hoje |
|---|---|---|
| C1 | Preços e formatos oficiais (site mostra "Em breve") | Admin informa por pacote |
| C2 | Prazo de cancelamento; falta desconta sessão? | Treinador decide caso a caso |
| C3 | Aluno pode cancelar sessão confirmada / remarcar? | Não (fala com o treinador) |
| C4 | Mensalidade: cobrança recorrente? | Uma baixa por pacote |

## D. Treinos e operação

| # | Pendência | Hoje |
|---|---|---|
| D1 | Aplicar várias semanas de uma vez / repetir semana | Uma semana por vez |
| D2 | FC máxima por esporte (corrida, ciclismo, natação) | Uma por atleta |
| D3 | Sugerir Treino 1–4 pelo pace | Escolha manual |
| D4 | Rascunho conta como "já tem treino" na tela de aplicar | Conta |
| D5 | Horário/turno em treinos do mesmo dia | Sem horário |
| D6 | Capacidade padrão (30) e limites presenciais | 30 on-line; presencial sem limite |

## E. Conteúdo, comunicação e site

| # | Pendência | Hoje |
|---|---|---|
| E1 | Avisos por e-mail ou WhatsApp? | Só dentro da plataforma |
| E2 | Páginas próprias de notícia/evento/resultado | Link externo opcional |
| E3 | Indicadores da Hero 2 automáticos? | Fixos (04, 02, 13, 49) |
| E4 | WhatsApp e Instagram do rodapé; formulário de contato | Links `#` |
| E5 | Textos de apoio (Heros 2, 8, 9), respostas das perguntas, texto legal do rodapé | `TODO` |
| E6 | Link "Entrar / Minha conta" no cabeçalho do site e CTAs para `/cadastro` | Não alterado (aguarda autorização) |
| E7 | Fonte oficial da marca | Fonte do sistema |

## F. Integrações (Sprint 9)

| # | Pendência | Hoje |
|---|---|---|
| F1 | **For All Sports:** documentação da API, credenciais, o que trocar | Sem informação |
| F2 | **Plataforma multiagentes:** onde publicar, contrato de chamada, o que os agentes podem ver/fazer | Roda no seu computador |
