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
