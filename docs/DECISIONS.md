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
