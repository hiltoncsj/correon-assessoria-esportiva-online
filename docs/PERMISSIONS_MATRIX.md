# PERMISSIONS_MATRIX — Sprint 0

## Estado atual

Não há autenticação, sessão, perfis ou autorização. Tudo no site é público. Esta matriz é **proposta** a ser aprovada; a aplicação das regras será sempre no backend (esconder botão não é controle de acesso).

## Perfis

Visitante · Atleta · Treinador · Administrador. (Perfis adicionais, como "financeiro" ou "editor de conteúdo", ficam como decisão pendente.)

## Matriz (C=criar, L=ler, E=editar, X=excluir/arquivar, A=aprovar, —=sem acesso)

| Recurso | Visitante | Atleta | Treinador | Admin |
|---|---|---|---|---|
| Landing, conteúdo publicado | L | L | L | L |
| Cadastro/login próprio | C | E (próprio) | E (próprio) | E |
| Perfil do atleta | — | L/E próprio | L apenas atletas vinculados à sua modalidade | C/L/E todos |
| Contratos e preços | — | L próprios | — (L resumo de situação do vínculo) | C/L/E; alterar preço legado só admin e com auditoria |
| Pagamentos e assinaturas | — | L próprios | — | L/E, conciliação, estorno |
| Prescrição de treino | — | L publicada própria | C/L/E/publicar só para atletas vinculados | L todos; exceção auditada |
| Treino realizado | — | C/E próprio (não sobrescrito por prescrição) | L dos vinculados | L |
| Carteira / capacidade | — | L (seu treinador) | L própria | C/E, exceção registrada |
| Personal: solicitar horário | — | C/L próprio | A/recusar das próprias solicitações | L/E |
| Agenda e disponibilidade | — | L própria | C/E própria | L/E todas |
| Resultados, notícias, eventos | L publicados | L; consentir uso de imagem/dados | — (sugerir, a definir) | C/E/publicar/agendar/arquivar |
| Usuários, papéis, permissões | — | — | — | C/E |
| Auditoria, relatórios, configurações | — | — | relatórios da própria carteira | L/E |

## Regras transversais

1. **Escopo por vínculo:** o treinador só acessa atletas com vínculo ativo na modalidade correspondente (um atleta pode ter mais de um treinador). Toda consulta filtra por vínculo no servidor.
2. **IDOR:** nenhuma rota confia em ID vindo do cliente sem checar propriedade/vínculo.
3. **Sessão:** cookie `HttpOnly; Secure; SameSite=Lax`, regeneração de ID no login, expiração por inatividade, CSRF token em todo POST.
4. **Senhas:** `password_hash` (Argon2id/bcrypt), limitação de tentativas, recuperação por token de uso único e curto.
5. **Auditoria:** ações administrativas, mudança de preço/contrato, atribuição de carteira e edição de prescrição gravam quem, quando, antes/depois.
6. **Preço legado R$ 120:** atribuído somente por admin/importação; nunca vem do front.
7. **LGPD:** consentimento registrado por finalidade (termos, privacidade, uso de imagem e resultados), exportação/exclusão a pedido, retenção definida. Dados de saúde/desempenho tratados como sensíveis.
8. **Agentes de IA:** operam com a identidade e o escopo do usuário que os aciona, nunca com permissão ampla própria; toda ação é auditada.

## Decisões pendentes

- Existe perfil de "recepção/financeiro" separado do admin?
- Treinador pode propor resultados/notícias?
- Atleta pode ver/editar o próprio histórico anterior ao cadastro?
