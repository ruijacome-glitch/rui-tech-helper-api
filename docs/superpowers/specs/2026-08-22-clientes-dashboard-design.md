# Sub-projecto 6 — Clientes + Dashboard

**Data:** 2026-08-22
**Repos:** rui-tech-helper-api (Laravel), rui-tech-helper-crm (React+Vite+TanStack)
**Depende de:** sub-projecto 5 (CRM SPA) — já em produção.
**Contexto:** primeiro sub-projecto da série 6-10 que reconstrói a IA do CRM segundo mockup fornecido pelo utilizador (5 ecrãs: Dashboard, Cliente-Detalhe, Intervenções, Nova Intervenção, Agendamentos). Este sub-projecto cobre apenas Dashboard + módulo Clientes (lista + detalhe).

## Fora de scope (sub-projectos futuros)

- 7: Intervenções (lista com filtros mockup-style) + Nova Intervenção (form/modal com agendamento)
- 8: Agendamentos (calendário semanal)
- 9: Equipamentos (inventário por cliente) + Documentos
- 10: Faturas + Comunicações + Relatórios + Definições

Sidebar do CRM passa a listar todos os itens do mockup (Dashboard, Clientes, Intervenções, Agendamentos, Equipamentos, Faturas, Orçamentos, Comunicações, Documentos, Relatórios, Definições). Itens ainda não construídos apontam para rota que mostra placeholder "Em breve" — mantém consistência visual com mockup completo sem esconder navegação.

## Decisões confirmadas com utilizador

1. Ticket→Intervenção: só copy no frontend. Backend mantém model/tabela/rotas `Ticket` inalterados.
2. Dashboard KPI "Agendamentos": placeholder estático `{ total: 0 }` (activa dados reais no sub-projecto 8).
3. KPI "Faturação (mês)": soma de `pagamentos.valor` onde `estado = 'pago'` e `created_at` no mês corrente.
4. KPI "Pendentes": contagem de tickets com `estado NOT IN ('resolvido', 'cancelado')`.
5. Cliente-detalhe mostra as 7 tabs do mockup imediatamente (Resumo, Intervenções, Equipamentos, Faturas, Orçamentos, Documentos, Comunicações) — 3 populadas com dados reais, 4 com empty-state "em breve" (sem tabela backend ainda: Equipamentos-inventário, Faturas, Documentos, Comunicações).

## Backend — rui-tech-helper-api

### `GET /api/admin/clientes`
- Auth: `sanctum` + role admin (mesmo padrão dos endpoints de tickets existentes).
- Query params: `search` (opcional, `LIKE` sobre `nome`, `email`, `telefone`).
- Paginado (`->paginate(20)`), mesma shape de resposta que `tickets` (`data`, `meta`).
- Cada linha: `id`, `nome`, `email`, `telefone`, `created_at`, `intervencoes_count` (via `withCount('tickets')`).
- Requer relação `Cliente::tickets()` (hasMany) — não existe ainda no model `Cliente`, adicionar.

### `GET /api/admin/clientes/{cliente}`
- Auth: idem. 404 se não existir (route model binding).
- Resposta:
  ```json
  {
    "cliente": { "id": 1, "nome": "...", "email": "...", "telefone": "...", "morada": "...", "nif": "...", "notas": "...", "created_at": "..." },
    "resumo": {
      "intervencoes_total": 12,
      "faturacao_total": 450.0,
      "ultima_intervencao_em": "2026-08-10T10:00:00Z"
    },
    "intervencoes": [ { "id": 1, "titulo": "...", "estado": "...", "categoria": "...", "prioridade": "...", "created_at": "..." } ],
    "orcamentos": [ { "id": 1, "ticket_id": 1, "valor_total": 120.0, "estado": "...", "created_at": "..." } ]
  }
  ```
- `intervencoes`: últimos 20 tickets do cliente, ordenados `created_at desc`.
- `orcamentos`: últimos 20 orçamentos ligados a tickets do cliente (join via `tickets.cliente_id`), ordenados `created_at desc`.
- `resumo.faturacao_total`: soma de `pagamentos.valor` com `estado='pago'` para pagamentos cujo orçamento pertence a um ticket do cliente.
- `resumo.ultima_intervencao_em`: `max(tickets.created_at)` do cliente, null se não houver.

### `GET /api/admin/dashboard`
- Auth: idem.
- Resposta:
  ```json
  {
    "clientes": { "total": 40, "novos_mes": 3 },
    "intervencoes": { "total": 120, "esta_semana": 8 },
    "faturacao_mes": 1250.50,
    "pendentes": 14,
    "agendamentos": { "total": 0 },
    "por_estado": { "aberto": 5, "em_analise": 2, "em_curso": 4, "aguarda_cliente": 1, "aguarda_peca": 1, "em_testes": 1, "resolvido": 100, "cancelado": 6 },
    "intervencoes_recentes": [ { "id": 1, "titulo": "...", "cliente_nome": "...", "estado": "...", "created_at": "..." } ]
  }
  ```
- `clientes.novos_mes`: clientes com `created_at` no mês corrente.
- `intervencoes.esta_semana`: tickets com `created_at >= now()->startOfWeek()`.
- `faturacao_mes`: soma `pagamentos.valor` onde `estado='pago'` e `created_at` no mês corrente.
- `pendentes`: contagem tickets `estado NOT IN (resolvido, cancelado)`.
- `por_estado`: `Ticket::groupBy('estado')->count()`, todas as 8 chaves do enum presentes mesmo a 0.
- `intervencoes_recentes`: últimos 5 tickets (`created_at desc`), com nome do cliente via `with('cliente')`.

### Ficheiros a criar/alterar
- Criar `app/Http/Controllers/Admin/ClienteController.php` métodos `index()`, `show(Cliente $cliente)` — adicionar aos existentes (`store()` mantém-se).
- Criar `app/Http/Controllers/Admin/DashboardController.php` método `index()`.
- Modificar `app/Models/Cliente.php`: adicionar `tickets(): HasMany`.
- Modificar `routes/api.php`: adicionar as 3 rotas sob grupo `admin` existente.
- Testes Feature: `tests/Feature/Admin/ClienteControllerTest.php` (index com search, show com 404, show com agregados correctos), `tests/Feature/Admin/DashboardControllerTest.php` (KPIs com fixtures conhecidas).

## Frontend — rui-tech-helper-crm

### Novas rotas
- `/` (ou `/dashboard`) → `DashboardPage`
- `/clientes` → `ClientesListPage`
- `/clientes/$clienteId` → `ClienteDetailPage`
- Rotas placeholder para módulos futuros (`/agendamentos`, `/equipamentos`, `/faturas`, `/orcamentos`, `/comunicacoes`, `/documentos`, `/relatorios`, `/definicoes`) → componente `PlaceholderPage` genérico ("Módulo em breve").

### `DashboardPage`
- 4 KPI cards reais (Clientes, Intervenções, Faturação mês, Pendentes) + 1 card "Agendamentos" com badge "em breve" e valor 0.
- Donut simples (SVG, sem lib nova) para `por_estado`.
- Lista "Intervenções recentes" (5 linhas, link para ticket).
- Usa `useQuery(['dashboard'], () => apiFetch('/api/admin/dashboard'))`.

### `ClientesListPage`
- Input de busca (debounce 300ms) → param `search`.
- Tabela: Nome, Email, Telefone, Nº intervenções, link para detalhe.
- Reusa padrão `TableSkeleton`/`EmptyState` já criado em `tickets-list.tsx` (extrair para `src/components/table/` partilhado, evita duplicar 3ª vez).

### `ClienteDetailPage`
- Header: nome, email, telefone, morada, nif.
- Tabs (componente simples baseado em estado local, sem lib nova): Resumo, Intervenções, Equipamentos, Faturas, Orçamentos, Documentos, Comunicações.
- Resumo: 3 stat cards (intervenções total, faturação total, última intervenção).
- Intervenções: tabela igual à de `tickets-list.tsx` mas filtrada (dados já vêm no payload do `show`).
- Orçamentos: tabela simples (id, valor, estado, data).
- Equipamentos/Faturas/Documentos/Comunicações: `EmptyState` com mensagem "Módulo em breve".

### Sidebar (`root.tsx`)
- Substituir lista actual (Tickets, Pagamentos, Conteúdo) por lista completa do mockup, usando "Intervenções" como label do link que aponta para `/tickets` (rota interna inalterada).
- Itens sem página real apontam para `PlaceholderPage`.

## Segurança
- Todos os 3 endpoints novos atrás de `auth:sanctum` + verificação de role admin (mesmo middleware/gate já usado nos endpoints admin existentes) — sem excepções, sem dados de outros clientes expostos a roles não-admin.
- Sem SQL raw — usar Eloquent/query builder parametrizado.
- `search` param sempre via `LIKE` com bindings (Eloquent `where(...,'like',...)`), nunca concatenado.

## Testes
- Backend: Feature tests para os 3 endpoints (casos: sucesso, 404, autorização — 403/401 para não-admin).
- Frontend: build (`npm run build`) + verificação manual das 3 páginas novas no browser dev server antes de dar como concluído.
