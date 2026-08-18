# Fundação backend — design

Data: 2026-08-19
Sub-projecto 1 de 5 (fundação → CRM core → financeiro → conteúdo site → frontend SPA).

## Contexto

"O Rui dos Computadores" — negócio local de reparação e manutenção informática em Cascais. Site público existente (`rui-tech-helper`, Vite/TanStack Start, gerido pelo Lovable, sync automático GitHub → Lovable). Vai crescer para incluir CRM (clientes, intervenções/tickets, financeiro) e portal de cliente. Este documento cobre só a fundação: base de dados, autenticação/roles, e pipeline de deploy — tudo o resto depende disto.

Restrição central: hosting cPanel partilhado, **sem acesso SSH**, só cPanel + FTP. PHP 8.5 disponível via MultiPHP Selector. cPanel tem "Git Version Control" mas **sem Terminal web** — logo sem forma de correr `composer install` no servidor.

## Arquitectura — 3 repositórios separados

1. **`rui-tech-helper`** (existente, gerido pelo Lovable) — site público + portal Cliente (login, ver estado de intervenções, pagar facturas pendentes, pedir novo serviço). Conversão para build estático (SPA) e ligação à API ficam para o sub-projecto 5; a fundação só prepara o backend que essas páginas vão consumir mais tarde.
2. **`rui-tech-helper-api`** (novo, este repo) — Laravel (PHP 8.5), MySQL. Deploy em `api.oruidoscomputadores.pt` via cPanel Git Version Control.
3. **`rui-tech-helper-crm`** (novo) — SPA backoffice para Admin/Técnico, subdomínio `crm.oruidoscomputadores.pt`, build estático (sem runtime Node em produção).

Isolamento deliberado: o Lovable só deve tocar no repo 1 (o que ele gera). Dados sensíveis (financeiro, gestão de clientes) ficam em repos que o Lovable nunca vê.

## Roles

Três papéis fixos nesta fase — coluna `role` enum em `users`, sem pacote RBAC (`spatie/laravel-permission` seria over-engineering para 3 papéis; revisitar só se um dia for preciso permissões granulares por técnico).

- **Admin** (Rui) — acesso total.
- **Técnico** — vê/gere intervenções atribuídas a si; sem acesso financeiro/configuração.
- **Cliente** — portal próprio: consulta estado de intervenções, paga facturas pendentes (liga a IfthenPay, sub-projecto 3), pede novo serviço.

## Modelo de dados (mínimo desta fase)

```
users
  id, name, email, password, role (enum: admin|tecnico|cliente), timestamps

clientes
  id, user_id (nullable — null até o cliente activar o portal), nome, telefone,
  email, morada, nif (preparado para faturação Moloni, sub-projecto 3), notas,
  timestamps

convites
  id, token, cliente_id, expires_at, used_at, timestamps

personal_access_tokens  (Laravel Sanctum, gerido pelo framework)
```

## Fluxos de autenticação

**Admin/Técnico**: conta criada manualmente (seed inicial do Rui como admin; Admin cria contas de Técnico no CRM). Login email+password, sessão Sanctum SPA (cookie httpOnly, domínio registável partilhado `oruidoscomputadores.pt` entre `api.` e `crm.`).

**Cliente**: fluxo de duas etapas, sem auto-registo público.
1. Rui cria ficha mínima em `clientes` (nome, telefone, o que já souber).
2. Sistema envia email via Resend com link de convite (token em `convites`).
3. Cliente abre o link, preenche os próprios dados (morada, nif, email) e define password.
4. Sistema cria `users` (role=cliente) ligado à ficha existente, sessão fica activa.

Middleware por grupo de rotas (`/api/admin/*`, `/api/tecnico/*`, `/api/cliente/*`) valida o role antes de qualquer handler.

## Deploy

- **Backend**: push para `main` no GitHub → cPanel Git Version Control faz pull. `vendor/` fica **comitado no repo** (sem alternativa sem shell/composer no servidor). `.env` nunca vai para o git — colocado uma vez manualmente via FTP/File Manager na docroot, preservado entre pulls (`.cpanel.yml` define o que é copiado do repo pulled para a docroot, sem tocar em `.env`).
- **Migrations**: correm localmente (`php artisan migrate`) contra o MySQL de produção — cPanel Remote MySQL com o IP do Rui na whitelist (actualizar a whitelist manualmente se o IP mudar).
- **CRM SPA**: build local (`vite build`), deploy também via cPanel Git Version Control — só ficheiros estáticos, sem vendor/composer envolvido.

## Erros e testes

Respostas de API no padrão Laravel: 422 validação, 401/403 autenticação/autorização, 500 logado server-side. Testes Pest/PHPUnit cobrindo login e o fluxo completo de activação de convite (criação de ficha → email → preenchimento → conta activa), corridos localmente antes de cada deploy. Sem CI remoto nesta fase (sem SSH para o disparar no servidor; corre-se local antes do push).

## Fora de âmbito (sub-projectos seguintes)

- CRM core: modelo de intervenções/tickets, atribuição a técnicos, estados.
- Financeiro: orçamentos, IfthenPay (pagamentos), Moloni (faturação).
- Conteúdo do site editável via backoffice (hoje hardcoded em `site.ts`/`paginas.ts`).
- Conversão do frontend público para build estático e ligação às APIs.
