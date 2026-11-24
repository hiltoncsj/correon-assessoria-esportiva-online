# INTEGRATIONS_MAP — Sprint 0

## Resultado da varredura

Nenhuma integração existe no repositório: sem chamadas `fetch`/HTTP, sem rotas de API, sem SDKs, sem variáveis de ambiente, sem referência a For All Sports, plataforma multiagentes ou gateways. O único serviço externo é o **MariaDB da LocalWeb**, ainda sem uso.

| Integração | Status | Observação |
|---|---|---|
| Gateway de pagamento | NÃO IMPLEMENTADA / DEPENDÊNCIA EXTERNA | Nenhum escolhido. Não ativar serviço pago sem aprovação. Precisa: assinatura de webhook, idempotência, recorrência, PIX/cartão tokenizado |
| For All Sports | DEPENDÊNCIA EXTERNA | Roadmap diz "existente"; não está neste repositório. Preciso: documentação de API, autenticação, ambiente de teste. Integração só por API, sem banco compartilhado |
| Plataforma multiagentes | DEPENDÊNCIA EXTERNA | Idem. Roadmap proíbe criar outro orquestrador. Preciso: repositório/URL, contrato de chamada, modelo de autenticação, como informar o usuário/escopo |
| E-mail transacional (SMTP) | NÃO IMPLEMENTADA | Necessário para recuperação de senha e avisos. Confirmar SMTP da LocalWeb |
| WhatsApp / Instagram | PLACEHOLDER | `href="#"` no Footer; falta número/perfil reais |
| CorreON Connect (Garmin etc.) | FORA DO ESCOPO | Só documentação futura |

## Princípios para quando houver acesso

- Contratos documentados e versionados (`/api/v1`), autenticação por chave/OAuth, timeouts e retentativas, log de chamadas sem dados pessoais.
- Webhooks: validar assinatura, rejeitar replays, registrar o evento bruto, processar de forma idempotente (`external_id` único).
- Agentes: expor apenas leituras/ações necessárias, com escopo do usuário e auditoria; segredos em variáveis de ambiente fora do `public_html`.

## Perguntas abertas (bloqueiam a Sprint 9 e parte da 6)

1. Onde estão o código/documentação de For All Sports e da plataforma multiagentes? Foram feitos em outro repositório/máquina?
2. Qual gateway será usado (Asaas, Mercado Pago, Pagar.me, Stripe, outro)?
3. A LocalWeb permite cron jobs, SSH/Composer, `mod_rewrite` e SMTP?
