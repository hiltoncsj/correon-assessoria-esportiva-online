# IMPLEMENTATION_BACKLOG — Sprint 0

Status: EXISTENTE E FUNCIONAL (EF) · EXISTENTE E INCOMPLETA (EI) · PRECISA DE CORREÇÃO (PC) · NÃO IMPLEMENTADA (NI) · DEPENDÊNCIA EXTERNA (DE).

## Inventário

| Área | Status | Nota |
|---|---|---|
| Landing (Header, Hero 1–9, Footer), tokens, motion | EF | Preservar; não reconstruir |
| Hero 6: UI de planos e regras de combo | EI | Só mensal; CONTRATAR → `#contato`; preços só de exibição |
| Hero 7: UI Resultados/Notícias/Eventos | EI | Listas vazias; sem admin, detalhe, consentimento |
| Hero 9 / contato | EI | Sem formulário/canal real |
| Footer: Whatsapp/Instagram, texto legal | EI | `href="#"`, `TODO` |
| Textos pendentes (`grep TODO src/components`) | EI | Heros 2, 8, 9, Personal, Footer |
| Fontes oficiais | EI | system-ui até chegar o arquivo da marca |
| Testes automatizados, lint, CI | NI | Só `npm run build` |
| Commit do trabalho atual | PC | ~45 arquivos sem commit; raiz fora do git |
| Limpeza de sobras (`Welcome.astro`, imagens sem uso, `design-system2/`) | PC | Remoção só com autorização |
| Backend, banco, auth, permissões | NI | |
| Áreas atleta/treinador/admin | NI | |
| Prescrição de treinos | NI | |
| Contratos, preços, pagamentos | NI / DE (gateway) | |
| Personal Trainer e agenda | NI | |
| Conteúdo (admin de Hero 7) | NI | |
| For All Sports, multiagentes | DE | |

## Backlog priorizado

**P0**
1. Decidir arquitetura (A/B), confirmar capacidades da hospedagem e estado do MariaDB. *(bloqueia tudo)*
2. Commitar o estado atual do site e criar estrutura `backend/` com migrações versionadas e ambiente local PHP 8.3.
3. Autenticação, cadastro, recuperação de senha, perfis, CSRF, rate limit, auditoria básica. *(Sprint 1)*
4. Dashboard do atleta com dados reais e estados vazios. *(Sprint 2)*
5. Prescrição/visualização de treinos com versionamento. *(Sprint 3)*
6. Painel do treinador e carteira com capacidade. *(Sprint 4)*
7. Admin: atletas, treinadores, vínculos. *(Sprint 5)*

**P1**
8. Catálogo, preços, contratos (preço aplicado, legado), gateway, webhooks, inadimplência. *(Sprint 6)*
9. Personal Trainer e agenda. *(Sprint 7)*

**P2**
10. Admin de resultados/notícias/eventos + API pública para a Hero 7 + consentimento. *(Sprint 8)*
11. For All Sports e multiagentes; testes e estabilização. *(Sprint 9)*

## Dúvidas e decisões pendentes

1. Arquitetura A (PHP server-side) ou B (SPA + API)?
2. O MariaDB tem dados? Existe lista de alunos antigos (R$ 120) para importar?
3. Troca Corrida↔Triathlon: livre, limitada, com histórico?
4. Preços trimestral/semestral/anual e do Personal.
5. Gateway de pagamento.
6. Destino dos botões CONTRATAR/COMEÇAR após existir cadastro (cadastro → escolha de plano → checkout).
7. Onde estão For All Sports e a plataforma multiagentes?
8. Textos jurídicos e política de retenção; responsável LGPD.
9. Capacidade do treinador (30) vale por modalidade ou total? Online e presencial separados.
10. Notificações: apenas no sistema ou também e-mail/WhatsApp?

## Plano da Sprint 1 — Autenticação, cadastro e permissões (proposta; aguardar aprovação)

**Escopo fechado:** cadastro, login/logout, recuperação de senha, três perfis, proteção de rotas no backend, auditoria básica, páginas `/entrar`, `/cadastro`, `/recuperar-senha`, `/app` e `/admin` vazios com guarda de acesso. Sem dashboard real, sem pagamentos.

**Pré-requisitos (decisões 1, 2 e acesso ao banco/SMTP).**

**Arquivos a criar (opção A):**
- `backend/public/index.php` (front controller), `backend/src/{Auth,Http,Db,Security,Audit}/…`, `backend/config/*.example.php` (segredos fora do git), `backend/migrations/0001_users_roles.sql` e `0002_audit_consent.sql` (apenas `CREATE`, sem `DROP`), `backend/views/{auth,layout}/…`, `backend/tests/…`.
- `docs/DECISIONS.md` (registro de decisões), `docs/DEPLOY_LOCALWEB.md` (como subir via FileZilla: `dist/` + backend, `.htaccess`).

**Arquivos a alterar:** `Header.astro` (links Entrar/Minha conta, somente com autorização), Hero 6/9 CTAs (destino para `/cadastro`, com autorização), `CLAUDE.md`.

**Critérios de aceitação:** cadastro cria só atleta; admin é criado por seed/CLI, nunca por formulário; senha com hash; login com limite de tentativas; atleta não acessa `/treinador` nem `/admin` (teste de 403 no servidor); sessão com cookies seguros e CSRF; recuperação com token de uso único; toda ação sensível gera `audit_log`; migrações reaplicáveis sem perda de dados; landing inalterada (build igual).

**Testes:** unitários de hash/token/permissões; testes HTTP de login, logout, 401/403, CSRF, rate limit, acesso cruzado entre atletas (IDOR); checklist manual em desktop e mobile.

**Riscos:** disponibilidade de e-mail/SMTP; paridade PHP local 8.0 × 8.3; segredos em hospedagem compartilhada.
