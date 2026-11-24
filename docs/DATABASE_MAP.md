# DATABASE_MAP — Sprint 0

## Estado atual

**Não existe banco de dados no repositório** (sem `.sql`, migrações, ORM, `.env`, scripts de seed). O MariaDB da LocalWeb (`correon.mysql.dbaas.com.br`) foi informado, mas não há esquema conhecido. **Pergunta aberta:** o banco está vazio ou já contém tabelas/dados (ex.: alunos antigos, contratos R$ 120)? Se houver dados, é preciso um dump somente-leitura antes da Sprint 1 (regra: não executar migrações destrutivas).

Os únicos "dados" hoje são arquivos TypeScript:

| Arquivo | Conteúdo | Destino futuro |
|---|---|---|
| `src/data/plans.ts` | modalidades, períodos, preço mensal R$ 150, combos R$ 200, grupo excludente Corrida/Triathlon, benefícios, formatos de Personal | tabelas `modality`, `product`, `price_table`, `bundle` |
| `src/data/comunidade.ts` | tipos de Resultado/Notícia/Evento; listas vazias | tabelas `result`, `news`, `event` + consentimento |

## Entidades previstas (proposta para validação, nada criado)

Convenções: InnoDB, `utf8mb4`, chaves `BIGINT UNSIGNED`, `created_at/updated_at`, FKs com `ON DELETE RESTRICT` (nada de exclusão em cascata de histórico), migrações versionadas e reversíveis sem perda de dados.

**Identidade e acesso (Sprint 1)**
`user` (e-mail único, senha hash, status) · `role` (atleta, treinador, admin) · `user_role` · `athlete_profile` · `trainer_profile` · `password_reset` · `login_attempt` · `audit_log` · `consent` (tipo, versão do texto, data, IP).

**Catálogo e comercial (Sprint 6)**
`modality` (corrida, triathlon, musculação) · `product` (digital/presencial) · `price_table` (produto, período, valor, vigência) · `contract` (atleta, modalidade, período, **preço aplicado**, `commercial_condition` = `standard` | `legacy_120`, datas, status) · `subscription` · `payment` (status do pagamento separado do status do contrato) · `payment_event` (webhook bruto, `external_id` único = idempotência) · `invoice`.

**Treinos (Sprint 3)**
`training_plan` · `plan_version` (versionamento) · `planned_workout` · `workout_item` (corrida/ciclismo/natação/exercícios: série, repetição, carga, intervalo) · `completed_workout` (nunca sobrescrito pela prescrição) · `workout_audit`.

**Carteira e capacidade (Sprint 4)**
`trainer_athlete` (vínculo por modalidade; atleta pode ter vários treinadores) · `trainer_capacity` (online/presencial, limite padrão 30, alerta 80%, bloqueio 100%) · `capacity_exception` (exceção administrativa registrada).

**Personal Trainer (Sprint 7)**
`personal_package` · `session_credit` (contratadas/usadas/restantes) · `availability` · `booking_request` (pendente/aprovada/recusada) · `session` (realizada/falta/cancelada/remarcada).

**Conteúdo (Sprint 8)**
`result` · `news` · `event` · `media_consent` · `notification`.

## Regras de integridade já decididas

- Preço contratado fica no contrato; a condição de legado **nunca** é escolhida pelo usuário e só é atribuída por admin ou por importação de contratos antigos.
- Corrida e Triathlon são mutuamente exclusivos por atleta (um contrato ativo de um OU de outro); a troca entre eles mantém o preço (regra do backend, ainda por especificar: livre ou limitada?).
- Combos (Corrida+Musculação, Triathlon+Musculação) são derivados de contratos individuais, não um produto separado; preço e desconto devem ser calculados no servidor.
- Nenhum dado de cartão é armazenado.
- Auditoria de ações administrativas e alterações de prescrição.

## Riscos

- Sem conhecimento do estado real do banco, qualquer modelagem é hipótese.
- Hospedagem compartilhada: limites de conexão e de permissões (ex.: `CREATE`/`TRIGGER`). Confirmar privilégios do usuário do banco.
- Backups: confirmar rotina de backup/restauração do MariaDB na LocalWeb.
