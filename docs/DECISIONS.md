# DECISIONS — registro de decisões

## 2026-11-24 (fim da Sprint 0)
1. **Arquitetura A:** Landing estática (Astro, `dist/`) ao lado de uma área logada em PHP 8.3 renderizada no servidor. API JSON só para webhooks, conteúdo público e integrações.
2. **Banco:** MariaDB da LocalWeb está vazio. Não há dados legados a importar (alunos antigos R$ 120 serão cadastrados depois, por admin).
3. **Hospedagem:** cron permitido. SSH/Composer, `mod_rewrite` e SMTP: a confirmar.
4. **Integrações:** a plataforma multiagentes roda na máquina do usuário e será colocada online; For All Sports já está online na LocalWeb. Integração só por API, sem banco compartilhado.
5. **Gateway:** a definir depois; preferência declarada pelo usuário: InfinitePay. Nada será ativado sem aprovação; verificar se a InfinitePay oferece cobrança recorrente e webhooks assinados antes da Sprint 6.
6. **Git:** o usuário criará um repositório remoto para sincronizar. Segredos (`.env`, `backend/config/*.php` reais) ficam fora do git.
