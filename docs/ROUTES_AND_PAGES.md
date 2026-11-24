# ROUTES_AND_PAGES — Sprint 0

## Rotas existentes

Apenas uma rota estática: `/` (`src/pages/index.astro`). Âncoras na mesma página:

| Âncora | Seção | Observação |
|---|---|---|
| `#conteudo` | `<main>` | link "Pular para o conteúdo" |
| `#sobre` | Hero 5 | Header e Footer |
| `#planos` | Hero 6 | Header e Footer. Botões CONTRATAR/COMEÇAR (Personal) apontam para `#contato` |
| `#movimento` | Hero 7 | só Header ("EM MOVIMENTO") |
| `#perguntas` | Hero 8 | Header e Footer |
| `#contato` | Hero 9 | CTA do Header ("COMEÇAR") e Hero 9; **não há formulário**, é só um link/âncora |
| `#top` | — | nenhum elemento com esse id |
| Footer WhatsApp/Instagram | — | `href="#"` (placeholder) |

## Páginas solicitadas no prompt × situação

| Página | Situação | Observação |
|---|---|---|
| Início | EXISTENTE E FUNCIONAL | `/` |
| Sobre | EXISTENTE E FUNCIONAL (como seção) | `#sobre`, não é página própria |
| Planos | EXISTENTE E INCOMPLETA | seção `#planos`; só mensal; "Contratar" sem destino real |
| Resultados / Notícias / Eventos | EXISTENTE E INCOMPLETA | abas na Hero 7, listas vazias, sem páginas de detalhe, sem admin |
| Contato | EXISTENTE E INCOMPLETA | só CTA, sem formulário nem canal real (WhatsApp/Instagram `#`) |
| Login | NÃO IMPLEMENTADA | |
| Cadastro | NÃO IMPLEMENTADA | |
| Recuperar senha, Termos, Privacidade | NÃO IMPLEMENTADA | textos jurídicos são `TODO` |
| Área do atleta (14 telas) | NÃO IMPLEMENTADA | |
| Painel do treinador (11 telas) | NÃO IMPLEMENTADA | |
| Painel administrativo (21 módulos) | NÃO IMPLEMENTADA | |
| Páginas de erro 404/403/500 | NÃO IMPLEMENTADA | |

## Mapa proposto de rotas (opção A de `PROJECT_ARCHITECTURE.md`, a validar)

- Público: `/`, `/entrar`, `/cadastro`, `/recuperar-senha`, `/termos`, `/privacidade`.
- Atleta: `/app` (dashboard), `/app/perfil`, `/app/modalidades`, `/app/treinos`, `/app/agenda`, `/app/evolucao`, `/app/assinaturas`, `/app/pagamentos`, `/app/personal`, `/app/notificacoes`.
- Treinador: `/treinador` (dashboard), `/treinador/atletas`, `/treinador/atletas/{id}`, `/treinador/prescricoes`, `/treinador/agenda`, `/treinador/disponibilidade`.
- Admin: `/admin` + módulos (atletas, treinadores, produtos, planos e preços, contratos, financeiro, personal, conteúdo, relatórios, permissões, auditoria).
- API/integração: `/api/v1/webhooks/{gateway}`, `/api/v1/public/{results|news|events}` (alimenta a Hero 7), `/api/v1/integrations/*`.

Observação: a Hero 7 hoje lê arquivo estático; para exibir conteúdo cadastrado no admin sem republicar, ela passará a buscar `/api/v1/public/*` (ou o build lerá a API).
