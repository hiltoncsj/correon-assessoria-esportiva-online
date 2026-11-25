# Revisão de segurança — estabilização (Sprint 9, parte 1)

Data: 2026-11-25. Revisão feita pelo próprio desenvolvedor (leitura de código, varredura e testes). **Não substitui uma auditoria independente** nem um teste de invasão.

## Verificado
| Item | Resultado |
|---|---|
| Rotas com autorização no servidor | Todas as ações exigem o perfil; recursos de terceiros respondem 404/403 (testado) |
| Injeção de SQL | Valores sempre parametrizados; trechos concatenados são constantes do código |
| XSS | Saídas escapadas; CSP sem script/estilo inline na área logada; Hero 7 insere só texto |
| CSRF | Token em todo POST; API pública é só leitura, sem cookies |
| Sessão | HttpOnly, SameSite, Secure em produção, regeneração no login, expiração, invalidação ao trocar senha/bloquear |
| Senhas e links | bcrypt; link de uso único, só o hash no banco; limite de tentativas |
| Upload de fotos | Tipo conferido pelo conteúdo, imagem regravada (sem metadados), fora da pasta pública |
| Arquivos sensíveis | Código e `.env` fora do `public_html`; pacote sem `.env`, testes ou dados |

## Corrigido nesta revisão
1. **Descoberta de e-mails pelo tempo de resposta:** e-mail inexistente demorava ~520 ms contra ~144 ms de um existente (hash fictício inválido). Agora os dois caminhos fazem o mesmo trabalho.
2. Parâmetros de URL em formato de lista (`?x[]=`) agora são descartados.
3. Redirecionamento interno recusa `\` e quebras de linha.
4. Página de erro funciona mesmo com o banco fora do ar.
5. Landing passou a enviar cabeçalhos de segurança, sem enfraquecer os da área logada.

## Riscos aceitos ou em aberto
| Risco | Situação |
|---|---|
| Trocar senha logado não pede a atual | Decisão do cliente; avisa por e-mail e desconecta as outras sessões |
| Sem segundo fator para administrador | Usar senha longa e única; avaliar 2FA |
| Bloqueio por tentativas pode ser usado para travar o login de alguém por 15 min | Conhecido |
| Limite por IP usa o IP visto pelo PHP | **Conferir na LocalWeb** se há proxy: se todos chegarem com o mesmo IP, o limite fica coletivo |
| Link de convite aparece na tela do admin | Para quando o e-mail não chega; tratar como senha |
| Cadastro informa que não foi possível criar a conta se o e-mail já existe | Mensagem genérica e limitada por IP |
| **PHP 8.3 não testado** (local é 8.0.11) | Checagem estática feita; rodar a suíte em 8.3 antes de publicar |
| LGPD: retenção, exclusão a pedido, exportação | Não implementado (decisão pendente) |
| Textos jurídicos | Ausentes |
