# DECISIONS — registro de decisões

## 2026-11-24 (fim da Sprint 0)
1. **Arquitetura A:** Landing estática (Astro, `dist/`) ao lado de uma área logada em PHP 8.3 renderizada no servidor. API JSON só para webhooks, conteúdo público e integrações.
2. **Banco:** MariaDB da LocalWeb está vazio. Não há dados legados a importar (alunos antigos R$ 120 serão cadastrados depois, por admin).
3. **Hospedagem:** cron permitido. SSH/Composer, `mod_rewrite` e SMTP: a confirmar.
4. **Integrações:** a plataforma multiagentes roda na máquina do usuário e será colocada online; For All Sports já está online na LocalWeb. Integração só por API, sem banco compartilhado.
5. **Gateway:** a definir depois; preferência declarada pelo usuário: InfinitePay. Nada será ativado sem aprovação; verificar se a InfinitePay oferece cobrança recorrente e webhooks assinados antes da Sprint 6.
6. **Git:** o usuário criará um repositório remoto para sincronizar. Segredos (`.env`, `backend/config/*.php` reais) ficam fora do git.

## 2026-11-24 — Sprint 1
- Área logada em PHP puro (sem Composer): `backend/src` (namespace `Correon`), views em `backend/views`, migrações SQL idempotentes em `backend/migrations` (`php backend/bin/migrate.php`).
- Perfis: `athlete`, `trainer`, `admin`. Cadastro público cria SOMENTE atleta; admin e treinador só por `backend/bin/create-user.php`.
- Sessão: cookie HttpOnly/SameSite=Lax (Secure em produção), regeneração no login, expira em 2 h de inatividade / 12 h absolutas. CSRF em todo POST. CSP restritiva (sem scripts/estilos inline).
- Senha: `password_hash` (bcrypt), mínimo 10 / máximo 72 bytes. Limite: 5 falhas por e-mail e 20 por IP em 15 min.
- Recuperação: token aleatório de 256 bits, só o hash SHA-256 no banco, validade 1 h, uso único. Resposta idêntica exista ou não o e-mail.
- Consentimento: registra aceite de Termos e Privacidade com versão `rascunho-0` (textos jurídicos ainda não existem; `/termos` e `/privacidade` mostram "em elaboração").
- Limitação conhecida: após redefinir a senha, sessões já abertas continuam válidas até expirar (máx. 2 h ociosas). Melhorar na Sprint 5 (versão de sessão).
- Limitação conhecida: o servidor atrás de proxy/CDN usaria o IP do proxy no limite de tentativas; hoje usa só REMOTE_ADDR.

## 2026-11-24 — Sprint 2 (área do atleta)
- Rotas do atleta: `/app` (dashboard), `/app/perfil`, `/app/senha`, e `/app/modalidades|treinos|treinador|assinaturas` como páginas "em breve". Nenhuma rota recebe id de usuário: todo acesso é ao próprio usuário da sessão (sem IDOR por construção).
- Dashboard sem números inventados: modalidades, treinos, treinador e assinatura mostram o estado vazio real, pois ainda não há tabelas (Sprints 3, 4 e 6). Só "Minha conta" e "% do perfil preenchido" são dados reais.
- Tabela `athlete_profiles`: telefone, nascimento (mín. 13 anos), cidade/UF, objetivo, histórico esportivo, contato de emergência. Não coleta dados de saúde (dado sensível; exigiria consentimento específico). Campos propostos por mim: revisar se faltam/sobram.
- Auditoria do perfil guarda só os nomes dos campos, nunca os valores.
- `users.session_version`: trocar ou redefinir a senha incrementa a versão e desconecta as demais sessões (fecha a limitação registrada na Sprint 1).
- Alterar e-mail não está disponível (decisão futura: exige reconfirmação).
- Alterar senha (logado) NÃO pede a senha atual, por decisão do cliente. Risco aceito: quem tiver uma sessão aberta (aparelho emprestado/roubado) pode trocar a senha e tomar a conta. Compensações: limite de 5 trocas/15 min, desconexão das demais sessões, auditoria `password_changed` e aviso por e-mail ao dono da conta.

## 2026-11-25 — Sprint 3 (prescrição e gestão de treinos)
Perguntas de produto não respondidas pelo cliente; adotei estes padrões (revisar):
- **Disciplinas:** Corrida → corrida; Musculação → musculação; Triathlon → natação, ciclismo ou corrida (um treino = uma disciplina).
- **Treino estruturado em linhas:** resistência = etapa, duração (min), distância (km), alvo (ritmo/zona), observação; musculação = exercício, séries, repetições, carga, intervalo (s), observação. Máx. 40 linhas. Texto livre só em "orientações gerais".
- **Vínculo treinador↔atleta por modalidade** (`trainer_athletes`), atribuído por `backend/bin/assign-trainer.php` até a tela de admin (Sprint 5). Sem contratos (Sprint 6), o vínculo é o que define a modalidade do atleta.
- **Autorização:** treinador só vê atleta/treino com vínculo ATIVO na modalidade; sem vínculo = 404 (não revela existência). Encerrar o vínculo remove o acesso do treinador, mas o histórico fica com o atleta.
- **Ciclo do treino:** rascunho → publicado (atleta só vê publicado) → cancelado. "Voltar a rascunho" e cancelar só sem registro do atleta.
- **Versionamento:** toda criação/alteração/publicação grava uma foto completa (JSON) em `workout_versions`, com autor e data. Edição simultânea é detectada por número de versão (conflito = recusa).
- **Planejado × realizado separados:** `workout_logs` (um por treino). Depois que o atleta registra, o treinador não pode mais alterar nem cancelar aquele treino. O atleta pode corrigir o próprio registro; a versão anterior vai para `workout_log_revisions`.
- **Registro só a partir do dia do treino**; não aceita data futura.
- **Calendário:** lista por mês (sem grade mensal ainda). Perfil do atleta para o treinador (objetivo, histórico, emergência) fica para a Sprint 4, com regra de privacidade.
- **Fora desta sprint:** notificações ao atleta quando o treino é publicado/alterado; modelos/biblioteca de treinos; duplicar treino; visão do admin sobre treinos (Sprint 5).

## 2026-11-25 — Sprint 3, ajuste: modelos de treino
- **Biblioteca de modelos por treinador** (`workout_templates` + `workout_template_items`): "Treino 1", "Treino 2"… Privados ao treinador que criou (outro treinador recebe 404). Um modelo tem uma modalidade e uma disciplina e exige pelo menos uma linha.
- **Aplicar modelos** (`/treinador/modelos/aplicar`): o treinador escolhe a data e, para cada atleta vinculado à modalidade, qual modelo ele recebe (ou "não aplicar"); pode publicar na hora ou salvar como rascunho. Validado no servidor (atleta vinculado, modelo próprio/ativo/da modalidade); se qualquer seleção for inválida, nada é criado.
- **Cópia independente:** cada atleta recebe uma linha em `workouts` (com `source_template_id`) e cópia das linhas. Editar um treino de um atleta não altera o modelo nem os outros atletas; editar o modelo não altera treinos já entregues (preserva o histórico). As regras da Sprint 3 continuam (versões, bloqueio após registro do atleta).
- **Anti-duplicidade:** reaplicar o mesmo modelo ao mesmo atleta na mesma data é ignorado (e informado).
- **Modelos não são excluídos, só arquivados** (treinos aplicados referenciam o modelo).
- **Em aberto (perguntar ao cliente):** o modelo é uma sessão única. Se "Treino 1..4" forem semanas ou ciclos com vários dias, é preciso um modelo "multi-dia" e aplicação em calendário. Também: atleta ter um "treino padrão" persistente (nível) em vez de escolher a cada aplicação.

## 2026-11-25 — Sprint 3, ajuste 2: semanas por nível, zonas de FC individuais e aplicar por treino
- **Modelo = semana.** "Treino 1..4" são variações **por nível do atleta** (ex.: Treino 1 = pace acima de 6:30/km). Cada modelo tem várias **sessões**, cada uma em um dia da semana (ex.: 2 intervalados + 1 longo). Campo livre "nível" descreve a faixa de pace (informativo; não há classificação automática por pace).
- **Aplicar por treino:** em `/treinador/modelos/aplicar` há um bloco por treino com a lista de atletas para marcar (Treino 1 → A, B, F, J; Treino 2 → C, D…). Um atleta só pode estar em um treino por semana (validado no servidor e na tela). Escolhe-se uma data; vale a semana de segunda a domingo. Cada sessão vira um treino do atleta no dia certo, como cópia independente.
- **Treino padrão do atleta (nível):** definido pelo treinador na página do atleta, por modalidade; já vem marcado ao aplicar.
- **Zonas de FC por atleta (7 zonas), definidas e ajustadas só pelo treinador.** Nos treinos o treinador escolhe a zona (Z1..Z7); cada atleta vê os bpm dele. Padrão (% da FC máxima): Z1 67–73, Z2 73–79, Z3 79–85, Z4 85–90, Z5 90–95, Z6 95–97, Z7 97–100. O treinador informa a FC máxima do atleta, usa "Calcular pelo padrão" e ajusta zona a zona (valores arredondados; fronteiras compartilham o mesmo bpm). Atleta vê as zonas em modo leitura no perfil.
- **Histórico preservado:** ao registrar um treino, as zonas vigentes são gravadas junto (`workout_logs.zones_snapshot`); alterar as zonas depois muda só os treinos ainda não realizados.
- **Distância do longo por atleta:** ajustada individualmente depois de aplicar (a distância depende do objetivo do atleta).
- **Auditoria de zonas** guarda só a contagem e o modo (calculado/manual), nunca os bpm (dado pessoal).
- **Design da área interna:** mantém as cores CorreON; botões e animações adaptam técnicas do design-system2 (brilho radial no botão, aperto no clique, cartões com reflexo e elevação, entrada com subida e atraso escalonado), só em CSS e desligadas com "reduzir movimento".
- **Em aberto:** classificação automática do nível pelo pace de referência do atleta; semanas com repetição automática (várias semanas de uma vez); comparação de zonas por esporte (ciclismo/natação têm FC máxima própria).
- **Aplicar modelos mostra só quem ainda não tem treino na semana.** A tela de aplicar é por semana (anterior/próxima). Atleta com qualquer treino não cancelado na modalidade e semana (publicado = "Enviado", rascunho = "Rascunho") sai da lista de pendentes e aparece em "Já com treino nesta semana". O servidor aplica a mesma regra: quem já tem treino na semana é ignorado (tela desatualizada ou envio duplicado), com aviso. Para trocar o treino de alguém, edite o treino dele na página do atleta.

## 2026-11-25 — Sprint 3, ajuste 4: sessões livres, semana montada por atleta, tela de Zonas de FC
Substitui, no ajuste 2, o "dia fixo por sessão" e o envio direto pela tela de aplicar.
- **Sessões sem dia e sem quantidade fixa.** O modelo tem quantas sessões o treinador quiser (teto técnico de 100). `weekday` fica vazio (coluna agora opcional). Sessão sem título vira "Sessão N".
- **Envio em 2 passos:** (1) semana + atletas por treino; (2) para cada atleta, grade de segunda a domingo escolhendo a sessão de cada dia (qualquer sessão, repetível; também de outros modelos do mesmo treinador/modalidade). Uma sessão por dia por atleta.
- **Dias do atleta** (`trainer_athletes.train_days`): definidos na página do atleta; pré-montam a grade (1ª sessão no 1º dia etc.). Se vazios, são lembrados no primeiro envio; envios seguintes não os sobrescrevem.
- **Tela "Zonas de FC"** (`/treinador/zonas`): todos os atletas do treinador, FC máxima e Z1..Z7 editáveis; "Calcular" por linha usa os percentuais padrão. Só grava linhas alteradas; uma linha inválida cancela tudo.
- **Formulários grandes:** `public/.user.ini` define `max_input_vars=5000` (enviar junto no deploy). Formulários de modelo e de zonas têm o campo `_end`: se o PHP cortar o envio, nada é gravado e o usuário é avisado. No servidor local usar `php -d max_input_vars=5000`.
- **Em aberto:** dois treinos no mesmo dia; repetir a semana anterior de um atleta; aplicar várias semanas de uma vez.

## 2026-11-25 — Sprint 3, ajuste 5: vários treinos no mesmo dia
- No passo 2, cada dia aceita **vários treinos** ("+ outro treino"), em qualquer ordem, inclusive a mesma sessão repetida (teto técnico de 10 por dia). Substitui a regra anterior de uma sessão por dia.
- Podem se combinar **modalidades diferentes** (corrida + musculação, natação + ciclismo + corrida etc.). **Regra mantida:** o treinador só prescreve nas modalidades em que está vinculado ao atleta (natação e ciclismo pertencem ao Triathlon). O envio é recusado inteiro se alguma modalidade não tiver vínculo.
- Os treinos do dia ficam na ordem escolhida no envio (não há horário).
- A lista de pendentes continua valendo pela **modalidade escolhida no passo 1** e pela semana: quem já tem treino nela sai da lista. Para acrescentar um treino a quem já recebeu, usar "Novo treino" na página do atleta.
- **Em aberto:** horário/turno por treino; acrescentar sessões de modelo a uma semana já enviada.

## 2026-11-25 — Sprint 3, ajuste 6: "Excluir treino" na tela de aplicar
- Em "Já com treino nesta semana" cada atleta tem **Excluir treino** (com confirmação). A ação **cancela** (não apaga) todos os treinos do atleta **na modalidade da aba e na semana exibida**, incluindo rascunhos e treinos criados avulsos, e o nome volta para a lista de pendentes. Histórico de versões e auditoria preservados; o atleta deixa de ver.
- Treino **já registrado** pelo atleta nunca é cancelado; nesse caso o atleta continua na lista de quem já tem treino, com aviso.
- Treinos de **outra modalidade** enviados na mesma semana não são afetados (excluir pela aba daquela modalidade).

## 2026-11-25 — Sprint 4: painel do treinador
- **Rotas:** `/treinador` (início), `/atletas`, `/acompanhamento`, `/agenda`, `/disponibilidade`, `/novidades` (além de modelos, aplicar e zonas). Toda consulta de treino junta o vínculo ativo do treinador na mesma modalidade.
- **Capacidade online:** atletas distintos com vínculo ativo; padrão 30 (`trainer_settings.online_limit`); alerta a partir de 80%; em 100% `assign-trainer.php` bloqueia (código de saída 2), salvo `--excecao="motivo"`, gravada em `capacity_exceptions` e na auditoria. Nova modalidade de quem já é atleta não consome vaga. Ajuste por `bin/set-capacity.php` até a tela do admin (Sprint 5).
- **Capacidade presencial:** campo separado, sem valor padrão e sem contagem até o Personal Trainer (Sprint 7). Não inventado.
- **Perfil do atleta para o treinador:** idade (não a data de nascimento), cidade, telefone, objetivo, histórico e contato de emergência, só com vínculo ativo; cada acesso é auditado (`athlete_profile_viewed`) e o atleta é avisado na tela "Meu perfil". **Revisar com o responsável LGPD** e refletir na Política de Privacidade.
- **Acompanhamento:** planejado × realizado, sem registro e esforço médio (1, 4 ou 12 semanas).
- **Agenda:** treinos da semana (compromissos presenciais na Sprint 7). **Disponibilidade:** horários por dia (online/presencial), sem sobreposição; ainda sem uso em agendamento.
- **Notificações:** "Novidades" calculadas dos registros dos atletas (30 dias), com contador desde a última visita. Sem e-mail/WhatsApp/push (decisão futura).

## 2026-11-25 — Sprint 5: painel administrativo
- **Rotas:** `/admin`, `/admin/atletas`, `/admin/treinadores`, `/admin/relatorios`, `/admin/auditoria`. Tudo exige perfil admin (verificado no servidor) e CSRF nos POST.
- **Atribuição de treinador** pela tela, com a MESMA regra do script (`Assignment`): limite por treinador, exceção só com motivo, auditoria com o admin que fez; a linha do treinador é travada para duas atribuições simultâneas não furarem o limite.
- **Novo treinador:** o admin informa nome e e-mail; o sistema gera link de uso único (48 h) para a pessoa criar a senha, envia por e-mail e mostra o link ao admin (o envio de e-mail na hospedagem ainda não foi confirmado). O banco guarda só o hash.
- **Contas:** bloquear desconecta na hora (`session_version`) e impede login; reativar devolve o acesso. "Gerar link de senha" para atletas e treinadores. **Contas de administrador não são geridas pela tela** (criar/bloquear só por linha de comando), por segurança.
- **Relatórios:** por treinador (4 semanas: atletas, publicados, realizados, sem registro, adesão) e por modalidade. **Auditoria:** filtro por ação e dia, 50 por página, somente leitura.
- **Fora desta sprint (dependem de etapas futuras):** produtos/planos/preços, contratos, assinaturas, financeiro e inadimplência (Sprint 6); Personal Trainer e agenda presencial (Sprint 7); notícias, eventos e resultados (Sprint 8); edição de modalidades, papéis personalizados e configurações gerais (sem necessidade concreta ainda).

## 2026-11-25 — Preços informados pelo cliente (substituem o mensal de R$ 150)
| Período | Total | Parcelamento |
|---|---|---|
| Mensal | R$ 180 | — |
| Trimestral | R$ 480 | até 3x de R$ 160 |
| Semestral | R$ 840 | até 6x de R$ 140 |
| Anual | R$ 1.440 | até 12x de R$ 120 |

Aplicado na Hero 6 (`src/data/plans.ts`). **Premissas a confirmar:** (1) valores valem para cada modalidade; (2) R$ 180 substitui os R$ 150 para novos alunos; (3) o combo de R$ 200 continua só no mensal — trimestral/semestral/anual combinados mostram a soma dos individuais; (4) juros do parcelamento não definidos (a página não afirma "sem juros"). Continuam em aberto: preço do combo nos demais períodos, aluno antigo (R$ 120/mês), se as parcelas são no cartão (total garantido) ou cobrança recorrente, preços do Personal Trainer, gateway, prazo de inadimplência e cancelamento.
- Informado pelo cliente: hoje há **13 alunos antigos, em 4 cidades** (condição legada a registrar na Sprint 6; ainda não definido como serão cadastrados nem se a cidade afeta preço ou atendimento).

## 2026-11-25 — Sprint 6: preços, contratos e pagamentos (baixa manual)
- **Preços** em `plan_prices` (centavos), editáveis em `/admin/precos`; combo só com preço no mensal. A página pública continua lendo `src/data/plans.ts`: mudar preço no admin NÃO muda o site (unificar por API na Sprint 8).
- **Contrato** guarda o valor aplicado, parcelas, condição e vigência; mudança de tabela não afeta contratos. Condições: tabela; **aluno antigo** (R$ 120, mensal de UMA modalidade); **valor negociado** (justificativa obrigatória) para casos não definidos. Só o admin cria contratos.
- Não sobrepõe contratos da mesma modalidade; Corrida e Triathlon nunca juntos; troca Corrida↔Triathlon mantém o valor (ação do admin, registrada).
- **Parcelas** mensais a partir do 1º vencimento (centavos restantes na última). **Pagamento** separado do contrato: baixa manual (data e forma), idempotente; estorno com motivo. Nada é apagado (`contract_events` + auditoria).
- **Renovação** manual, mantendo produto, período, VALOR e condição. **Premissa:** aluno novo também renova pelo valor anterior, mesmo que a tabela tenha mudado (confirmar).
- **Cancelamento:** motivo obrigatório; parcelas abertas canceladas; sem multa.
- **Não implementado (regras/decisões pendentes):** gateway, cobrança recorrente e webhooks (tabela já tem `external_id` único para idempotência); contratação on-line pelo aluno; suspensão por inadimplência; multa/prazo mínimo; preços de Personal.
- Nenhum dado de cartão é armazenado.

## 2026-11-25 — Descontos definidos pelo cliente
- **Combo** (Corrida ou Triathlon + Musculação): **15%** sobre a soma, em todos os períodos: mensal R$ 306, trimestral R$ 816, semestral R$ 1.428, anual R$ 2.448. **Substitui o combo mensal de R$ 200.** No site vale quando as duas modalidades estão no mesmo período.
- **Desconto por período** exibido em relação ao mensal, com 2 casas: trimestral 11,11%, semestral 22,22%, anual 33,33%.
- Aplicado em `src/data/plans.ts` (site) e na tabela `plan_prices` (migração 0011). Contratos existentes não mudam.
- Confirmado pelo cliente: no **combo**, o desconto exibido é em relação ao **mensal sem combo** (R$ 360/mês): mensal 15,00%, trimestral 24,44%, semestral 33,89%, anual 43,33%. Preços do combo não mudam.

## 2026-11-25 — Sprint 7: Personal Trainer e agenda
- Produto independente dos planos on-line (`pt_packages`, `pt_sessions`). Formatos: pacote, mensalidade (frequência semanal, validade obrigatória), avulsa. **Preço, duração, quantidade, validade e local são informados pelo admin** (nada presumido).
- Fluxo: atleta pede horário → treinador confirma/recusa → treinador registra realizada/falta/cancelada. Pedido só dentro da **disponibilidade presencial** do treinador; sem sobreposição (treinador: confirmadas; atleta: pedidas e confirmadas); respeita saldo e frequência semanal.
- **Falta/cancelamento descontar sessão:** escolha do treinador em cada caso (regra comercial não definida). Realizada sempre desconta.
- Atleta só desiste de pedido ainda não respondido; para sessão confirmada, fala com o treinador (regra não definida). Remarcar = cancelar e pedir de novo.
- Capacidade **presencial**: atletas distintos com Personal ativo; limite opcional por treinador; excedente só com motivo (`capacity_exceptions`).
- Pagamento do Personal: baixa manual única por pacote; aparece separado no financeiro. A seção pública de Personal segue "Em breve" (sem preços).
- **Em aberto:** preços/formatos oficiais, prazo de cancelamento, avisos por e-mail/WhatsApp, cobrança mensal recorrente da mensalidade.

## 2026-11-25 — Sprint 8: conteúdo, consentimento e avisos
- **Hero 7 dinâmica:** o admin publica em `/admin/conteudo`; a página busca `/api/v1/public/comunidade` (pública, somente leitura, sem cookies, só campos exibidos) e mantém o conteúdo do build se a API falhar. `src/data/comunidade.ts` continua como reserva (vazio). Inserção sem `innerHTML`.
- **Consentimento:** atleta cadastrado só é publicado com autorização ativa dele (`/app/privacidade`); retirada tira do ar na hora (a foto tem cache de até 5 min). Resultado de não cadastrado e qualquer foto exigem declaração do admin.
- **Agendamento** por data/hora (sem cron). Nada é excluído: arquiva-se.
- **Fotos:** só JPG/PNG/WebP até 4 MB; regravadas e reduzidas (1280px), sem metadados; guardadas fora da pasta pública e servidas só se o conteúdo estiver visível.
- **Avisos** na plataforma: treino publicado/atualizado, semana enviada, resposta de Personal e comunicados do admin (todos ou por modalidade). Sem e-mail/WhatsApp.

## 2026-11-25 — Estabilização (Sprint 9, parte 1) e contas
- **Segurança:** ver `SECURITY_REVIEW.md`. **Publicação:** `npm run deploy:package` (ver `DEPLOY_LOCALWEB.md`). **Backup:** `BACKUP.md`.
- Integrações (For All Sports, multiagentes) continuam em espera; nada foi publicado.
- **Senha provisória (pedido do cliente):** ao cadastrar treinador o admin pode definir uma senha provisória (10+); no primeiro acesso a troca é obrigatória antes de qualquer tela. Em branco, continua o link de uso único.
- **Excluir conta de treinador (pedido do cliente):** sem registros = apagada; com histórico = desativada, fora das listas, e-mail liberado, histórico preservado (nome com "(excluído)"). Recusada se houver atletas, Personal ativo ou sessões pendentes. Não vale para atletas nem administradores (exclusão de dados de atleta é decisão LGPD pendente).

## 2026-11-25 — Modalidades do treinador, cadastro de atleta pelo admin e retorno ao site
- **Modalidades do treinador** (`trainer_modalities`): marcadas no cadastro ou na página do treinador. A atribuição só aceita modalidade atendida (tela, script e servidor); não se retira modalidade com atletas ativos; o treinador cria modelos nas modalidades que atende. Treinadores existentes foram preenchidos pelas modalidades com atletas ativos.
- **Admin cadastra atleta** com senha provisória (10+) e, opcionalmente, já vincula treinador. No primeiro acesso o atleta troca a senha e **ele mesmo aceita os Termos** (o admin não aceita por ele); recuperar a senha por e-mail não pula o aceite.
- **Voltar para o site:** link nas telas de entrada e no logotipo. Em produção é `/`; no computador local, `SITE_URL` no `.env` (o roteador de desenvolvimento também redireciona `/`).
- **Cabeçalho do site:** o botão "COMEÇAR" (ia para `#contato`) passou a ser **"ENTRAR"** e leva à área restrita: `/entrar` em produção, `http://localhost:8080/entrar` no computador local.
- **Alterar senha para todos os perfis:** atleta (`/app/senha`), treinador (`/treinador/senha`) e admin (`/admin/senha`) usam a mesma tela e as mesmas regras (`Account`): sem pedir a senha atual (decisão do cliente), limite de tentativas, desconexão das outras sessões, auditoria e aviso por e-mail.
- Observação: o teste de tempo de resposta do login (enumeração de e-mails) pode oscilar com a máquina carregada; falhou uma vez e passou na repetição, sem mudança no login.

## 2026-11-26 — Objetivos e provas
- Tabela `athlete_goals` (prova ou objetivo; corrida, triathlon, natação, ciclismo, trail, outro). Nada é apagado: usa-se "Cancelado".
- Atleta cadastra e edita os próprios; treinador com vínculo ativo em **qualquer modalidade** vê, cadastra e edita os dos seus atletas (escolha padrão proposta ao cliente; pode ser restringida por modalidade). Admin só consulta. Sem vínculo: 404. O treinador não cadastra provas próprias.
- **Hero 2 ligada ao cadastro:** `GET /api/v1/public/indicadores` devolve só o total. Contam os registros não cancelados de atletas ativos **com treinador vinculado** (cadastro avulso não infla o número). O site mostra **o maior** entre 49 (valor aprovado) e o total do sistema, para não cair a zero nem somar em dobro enquanto os objetivos antigos não forem lançados. Sem JavaScript ou sem API: 49.

## 2026-11-26 — Objetivos passam a ser uma lista única (substitui o item anterior)
- Pedido do cliente: **qualquer treinador adiciona, edita e remove**; **cada atleta coloca e tira o próprio nome**. Tabelas `goals` e `goal_participants` (0018); `athlete_goals` (0017) fica sem uso, sem apagar nada.
- Remover não apaga (`removed_at`); sair não apaga (`left_at`). Meta e resultado são por atleta e preenchidos por ele.
- Privacidade (regra do projeto: treinador só acessa atletas autorizados): treinador vê o total de inscritos e os nomes apenas dos seus atletas; atleta vê só o total.
- Hero 2: o total agora é o número de objetivos/provas não removidos (continua valendo o maior entre 49 e o total).

## 2026-11-26 — Loja de uniformes
- Admin cadastra produtos (foto, preço, tamanhos com estoque); só **atletas** veem e compram; carrinho na sessão; relatório em `/admin/vendas`, separado dos cadastros e das mensalidades.
- **Sem gateway:** Pix, link de pagamento (https) ou na entrega, com confirmação manual pelo admin. Nenhum dado de cartão. Integração automática (ex.: InfinitePay) depende de aprovação.
- O pedido reserva o estoque (baixa condicional em transação); cancelar devolve uma única vez. Pedido pago ou entregue não é cancelado (desfazer o pagamento antes). Não há fluxo de troca/devolução.
- Limites: 10 peças por tamanho no carrinho e 5 pedidos sem pagamento por atleta. Não há prazo para expirar pedido não pago: o admin cancela.
- **Excluir produto:** sem pedidos, é apagado; com pedidos, sai da loja e das listas (`deleted_at`, 0020) e o histórico de vendas permanece.
- **Fotos do produto:** até 8, pelo menos uma obrigatória; cada uma tem ícone de excluir (a última não sai); a primeira é a capa. `post_max_size` de produção passou para 36M no `.user.ini`. Tabela `product_images` (0021).

## 2026-11-26 — Resultado da prova e lembrete por e-mail
- O atleta registra o resultado **a partir do dia da prova** (antes, só a meta).
- No dia seguinte, e-mail em HTML com logo para **todos os inscritos** em provas (não em objetivos), uma vez por prova; quem já preencheu recebe texto de conferência. Alcança até 7 dias se a tarefa falhar. Mudar a data da prova reabre o lembrete. Aviso também dentro da plataforma.
- Disparo por URL protegida (`CRON_TOKEN`), compatível com agendador de hospedagem sem SSH. Migração 0022.
- **Tipo "Desafio"** em Objetivos e provas: data opcional; não recebe o e-mail do dia seguinte (só "Prova").
- **Data de início e de fim** (0023): fim opcional e nunca antes do início. A data de fim (ou a de início, sem fim) decide quando vira "Anteriores", quando o resultado é liberado e quando sai o lembrete.
- **Só "Meu resultado":** a pedido do cliente, o campo de meta saiu das telas e o resultado pode ser preenchido a qualquer momento (substitui a liberação por data). A coluna `target` permanece no banco, sem uso. O lembrete por e-mail não muda.
- **Sair** leva à página principal do site (`SITE_URL`; `/` em produção), não mais à tela de entrada.

## 2026-11-26 — Excluir conta de atleta
- Botão na página do atleta. **Recusa** com contrato vigente, cobrança em aberto, Personal ativo/marcado ou pedido em aberto.
- Sem histórico: apaga. Com histórico: encerra os vínculos, tira o nome dos objetivos, retira a autorização de publicação, apaga o perfil (telefone, nascimento, emergência), bloqueia, anonimiza o e-mail e mantém treinos, contratos, pagamentos e pedidos. O nome permanece no histórico com "(excluído)": um pedido de eliminação pela LGPD pode exigir tratamento adicional.
- **Loja do treinador** (`/treinador/loja`, `/treinador/pedidos`): compra como o atleta, com os mesmos limites e pedidos próprios. Produtos continuam geridos só pelo admin. Em `orders`, `athlete_id` é o comprador.

## 2026-11-26 — Promoção temporária no topo do site
- Admin → Promoção: título, texto e botão opcionais, início e fim (data e hora, Brasília), imagem (4 MB) ou vídeo MP4/WebM (30 MB). Tabela `promotions` (0024).
- Site: faixa com cronômetro (relógio do servidor) + mídia acima do cabeçalho; sobe com a rolagem; some ao zerar. Fora do período nada é inserido. Sem JavaScript ou sem API, a página fica como sempre.
- Com mais de uma no período, vale a que começou por último. Vídeo sem som, em repetição, com controles; não toca sozinho com movimento reduzido.
- `.user.ini`: `upload_max_filesize` 32M. O limite real da hospedagem precisa ser confirmado.

## 2026-11-26 — Desconto da promoção no plano anual
- Percentual opcional em Admin → Promoção (coluna `promotions.annual_discount_pct`, 0025). Vale só durante a promoção em vigor.
- Todos os preços anuais, **somado** ao desconto do combo (calculado sobre o preço anual já com combo).
- Vale no site (Hero 6) e no contrato anual novo a "Preço de tabela" (conta o momento da criação). 
- **Renovação de contrato feito com o desconto volta ao preço de tabela atual** (pedido do cliente), mesmo com promoção no ar. Os demais contratos continuam renovando pelo valor anterior.

## 2026-11-26 — Layout da promoção (pedido do cliente)
- Cronômetro acima da arte; os dois ocupam uma tela. Computador e celular deitado: arte inteira à esquerda (sem corte nem margem), texto e botão à direita. Celular em pé: arte em cima, texto e botão embaixo.
- Fundo da área: `src/assets/hero10-imagem1.jpeg`. Botão transparente, com destaque no mouse e no foco.
- Imagem recomendada: 1920 × 1080; o envio da promoção reduz para no máximo 1920 px de largura (demais fotos: 1280).
- Link "Pular para o conteúdo" removido do site público (perde-se o atalho de teclado).
- Testes: `backend/storage/uploads` é a mesma do desenvolvimento; a suíte só apaga o que ela criar.
