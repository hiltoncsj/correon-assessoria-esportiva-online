# PROJECT_ARCHITECTURE — Sprint 0 (Discovery)

Data: 2026-11-24. Somente diagnóstico; nenhum comportamento foi alterado.

## 1. Estado atual (AS-IS)

| Item | Situação |
|---|---|
| Aplicação | `SiteCorreOn/`: Astro 7.4.x, site **100% estático** (`npm run build` → `dist/`) |
| Dependências | Uma só: `astro`. Sem framework de UI, sem lib de estado, sem testes, lint ou formatador |
| Páginas | Uma: `src/pages/index.astro` (Header + Hero 01–09 + Footer) |
| Backend | **Inexistente** (nenhum `.php`, API, endpoint, serviço, worker) |
| Banco de dados | **Inexistente** (nenhum `.sql`, migração, ORM, `.env`) |
| Autenticação / sessões / permissões | **Inexistentes** |
| Pagamentos | **Inexistentes** (nenhum gateway, webhook, SDK) |
| For All Sports / plataforma multiagentes | **Nenhum vestígio no repositório** (busca por nomes, `fetch(`, `/api/`) |
| Git | `SiteCorreOn/` tem 1 commit ("Initial commit from Astro") e ~45 arquivos sem commit. A raiz `C:\SiteCorreon` **não** é repositório |
| Hospedagem alvo | LocalWeb: PHP 8.3, MariaDB (`correon.mysql.dbaas.com.br`), domínio + SSL, upload por FileZilla, sem Docker, sem Node em produção (assumido; **confirmar**) |
| Ambiente local | Node >=22.23.4; XAMPP com PHP 8.0.11 e MariaDB 10.4.21 (versões abaixo da produção) |

### Estrutura de `SiteCorreOn/src`

- `components/`: `Header`, `Hero01`–`Hero09`, `Footer` (+ `Welcome.astro`, sobra do template, sem uso).
- `layouts/Layout.astro`, `pages/index.astro`.
- `styles/tokens.css` (tokens de cor, tipografia, espaço, raio, sombra, z-index), `styles/motion.css`.
- `scripts/motion.ts` (text-roll, reveal de títulos).
- `data/plans.ts` (preços e regras comerciais da Hero 6) e `data/comunidade.ts` (Resultados/Notícias/Eventos da Hero 7, listas vazias de propósito).
- `assets/` (imagens; vários arquivos de heros antigos sem uso).

### Padrões reutilizáveis (design system)

Tokens CSS centrais, marca `#01cd52` / `#000000` / `#ffffff` (amostrada do arquivo oficial), botão `.btn` (pílula verde), cards, abas ARIA, foco visível, `prefers-reduced-motion`, melhoria progressiva (`html.js`). Fontes: stack `system-ui` (não há fonte oficial). **Não há** componentes de sidebar, tabela, formulário, modal, calendário ou notificação: precisam ser criados na área logada, reaproveitando os tokens.

## 2. Decisão arquitetural pendente (precisa de aprovação)

A hospedagem não roda Node. A "parte interna" precisa rodar em **PHP 8.3 + MariaDB**. Duas opções:

| | A) PHP renderizado no servidor (recomendada) | B) SPA (Astro/JS) + API PHP |
|---|---|---|
| Como | Landing estática em `public_html/`; áreas `/app`, `/treinador`, `/admin` em PHP (templates) com sessão por cookie | Front compilado consumindo API JSON |
| Prós | Funciona no plano compartilhado, 1 deploy simples (FileZilla), sessão/CSRF mais simples, menos peças | Reuso de componentes Astro, API pronta para integrações |
| Contras | Componentes da landing não reaproveitáveis diretamente (só tokens/CSS) | Mais complexidade, autenticação por token/cookie + CORS, maior superfície de ataque |

Recomendação: **A**, com camada de serviços PHP separada dos templates e rotas `/api/v1/*` JSON apenas para webhooks e integrações (For All Sports, multiagentes). Sem Composer pesado: poucas dependências (router leve, PDO nativo, biblioteca de hash nativa `password_hash`). A confirmar: o plano LocalWeb permite Composer/SSH, cron jobs, `mod_rewrite` e envio de e-mail (SMTP)?

## 3. Riscos técnicos

1. **Nenhuma base para reaproveitar** além da landing; o roadmap supõe funcionalidades "existentes" que não estão neste repositório (ver `INTEGRATIONS_MAP.md`).
2. Trabalho todo sem commit: risco de perda. Recomendado commitar o estado atual antes da Sprint 1.
3. Hospedagem compartilhada: sem filas/workers; webhooks e cobranças recorrentes dependem de cron e de PHP síncrono.
4. Segurança de dados pessoais e de saúde/desempenho (LGPD) em hospedagem compartilhada: exige HTTPS obrigatório, cookies `Secure/HttpOnly/SameSite`, backup do MariaDB e política de retenção.
5. Paridade de ambiente: XAMPP local (PHP 8.0 / MariaDB 10.4) difere da produção (PHP 8.3). Atualizar o PHP local ou testar sintaxe/recursos compatíveis.
6. Regras de preço hoje no front (`plans.ts`) são só exibição; a fonte da verdade precisa migrar para o banco (preço de tabela vs preço contratado, marca de legado R$ 120 só no contrato).
7. Landing estática não "sabe" de eventos/resultados/notícias: precisará de API ou rebuild.

## 4. Dependências externas

- Credenciais e documentação de **For All Sports** e da **plataforma multiagentes** (não estão no repositório).
- Escolha do **gateway de pagamento** (não definida; não ativar nada pago sem aprovação).
- SMTP/e-mail transacional para recuperação de senha.
- Preços trimestral/semestral/anual e do Personal Trainer (não definidos).
- Textos jurídicos (Termos, Privacidade, consentimentos), que hoje são `TODO`.
