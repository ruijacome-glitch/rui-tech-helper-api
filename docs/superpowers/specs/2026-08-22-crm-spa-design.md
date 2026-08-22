# CRM SPA (rui-tech-helper-crm) — Design Spec

Sub-project 5 do roadmap CRM "O Rui dos Computadores". Ver `docs/superpowers/plans/` anteriores (fundacao-backend, crm-core, financeiro, conteudo-configuravel) para contexto acumulado.

## Objectivo

Backoffice SPA para admin e técnico, em `crm.oruidoscomputadores.pt`, consumindo a API Laravel já existente (`rui-tech-helper-api`). Cliente final não usa este SPA — o portal de cliente vive no site público Lovable (`rui-tech-helper`), fora de âmbito aqui.

## Dois repos afectados

1. **`rui-tech-helper-api`** — adicionar endpoints de leitura (list/show) que hoje não existem. A API actual só tem `store`/`update`; um SPA de gestão precisa listar e inspeccionar.
2. **`rui-tech-helper-crm`** — repo novo, ainda não existe no disco. SPA React que consome a API acima.

## Stack (rui-tech-helper-crm)

- React + Vite + TanStack Router — mesma família tecnológica do site público (`rui-tech-helper` usa TanStack Start), reaproveita padrões e pode copiar componentes shadcn/ui já validados lá.
- Build estático puro (`vite build`), sem SSR — cPanel shared hosting não tem runtime Node. Nenhuma das razões que justificam TanStack Start (SSR) no site público se aplica aqui: o CRM é 100% autenticado, sem SEO a proteger.
- Estado servidor: TanStack Query (mesma lib já usada no site público via `useConteudoSite`).
- Auth: cookie de sessão Sanctum SPA já implementado no backend — `POST /login`, `GET /me`, `POST /logout`, mesmo domínio `oruidoscomputadores.pt` (cookie funciona cross-subdomain sem CORS/token manual).

## Segurança — normas aplicadas a todo este sub-projecto

- Toda a autorização é verificada **no backend** (middleware `role:admin`/`role:tecnico` + scoping por `tecnico_id` no query), nunca só no cliente — o SPA esconder um botão não substitui um `403` real na API.
- Sanctum SPA cookie: `HttpOnly`, `Secure`, `SameSite=Lax` (já configurado na fundação) — sem tokens em `localStorage`.
- Rate limiting no `/login` (já existe `throttle:5,1` no login admin Blade; aplicar o mesmo throttle ao `POST /login` da API se ainda não tiver).
- Validação de input em todos os endpoints novos (`FormRequest` ou `$request->validate()`), incluindo parâmetros de filtro (`estado`, `categoria`, etc. validados contra os Enums, não passados directo para a query).
- Nenhuma credencial/URL sensível hardcoded no frontend além da URL pública da API (já é o padrão do site público hoje).
- Dependências: `npm audit` limpo antes do primeiro deploy; mesma disciplina teria sido aplicada aos outros repos.
- CSRF: Sanctum exige `X-XSRF-TOKEN` em pedidos state-changing vindos de SPA — o cliente HTTP do CRM tem de ler o cookie `XSRF-TOKEN` e enviá-lo (biblioteca de fetch a configurar para isto, não é automático).

## Backend — novos endpoints (rui-tech-helper-api)

Todos dentro dos grupos de middleware `role:admin` / `role:tecnico` já existentes em `routes/api.php`.

| Método | Rota | Role | Descrição |
|---|---|---|---|
| GET | `/admin/tickets` | admin | Lista todos, filtros query-string: `estado`, `categoria`, `prioridade`, `tecnico_id` |
| GET | `/tecnico/tickets` | tecnico | Lista só os atribuídos ao user autenticado (`tecnico_id = auth()->id()`), mesmos filtros exceto `tecnico_id` |
| GET | `/admin/tickets/{ticket}` | admin | Detalhe: ticket + `cliente`, `tecnico`, `eventos` (ordenados desc), `anexos`, `orcamentos.itens`, `orcamentos.pagamento` |
| GET | `/tecnico/tickets/{ticket}` | tecnico | Igual, mas 403 se `ticket->tecnico_id !== auth()->id()` |
| PATCH | `/admin/tickets/{ticket}/atribuir` | admin | `tecnico_id` no body, valida que o user existe e tem role tecnico |
| GET | `/admin/orcamentos` | admin | Lista todos os orçamentos, filtro `estado`, com `ticket` e `itens` eager-loaded |
| GET | `/admin/pagamentos` | admin | Lista todos os pagamentos, filtro `estado`, com `orcamento.ticket` eager-loaded |
| GET | `/admin/tecnicos` | admin | `User::where('role', 'tecnico')->get(['id','name'])` — só para popular dropdown de atribuição, sem criar/editar |

Todas as respostas em JSON, paginadas (`->paginate(20)`) exceto `/admin/tecnicos` (lista curta, sem paginação). Reaproveitar os Enums existentes (`TicketEstado`, `TicketCategoria`, `TicketPrioridade`, `OrcamentoEstado`, `PagamentoEstado`, `PagamentoMetodo`) tal como estão — nenhum valor novo necessário.

## Páginas v1 (rui-tech-helper-crm)

1. **Login** — form email+password contra `POST /login`, redirect para `/tickets` em sucesso.
2. **Tickets — lista** — tabela com filtros (estado, categoria, prioridade; admin também filtra por técnico), paginação. Admin vê todos, técnico vê só os seus.
3. **Tickets — detalhe** — dados do ticket, timeline de eventos, anexos (download), lista de orçamentos, form mudar estado, form atribuir técnico (admin only).
4. **Orçamentos — criar/editar** — a partir do detalhe do ticket: form de itens (descrição, quantidade, preço unitário), submete para `POST /{role}/tickets/{ticket}/orcamentos` (já existe). Ver estado/decisão do cliente quando disponível.
5. **Pagamentos — lista** — tabela de pagamentos com estado, admin pode "marcar pago manual" (`POST /admin/orcamentos/{orcamento}/pagamento/marcar-pago`, já existe).
6. **Conteúdo site** — não recriado no SPA. Link de navegação abre `/admin/conteudo` (Blade, já funcional) em `api.oruidoscomputadores.pt` numa nova aba.

Fora de âmbito nesta fase (confirmado): gestão de contas técnico (criar/convidar) — continuam criadas manualmente.

## Deploy

- Subdomínio `crm.oruidoscomputadores.pt` não existe — criar via cPanel MCP (`create_subdomain`), Document Root a apontar directamente para a pasta com o build estático (sem PHP, sem `.output`/pasta pública Laravel envolvida — mais simples que o setup da API).
- cPanel Git Version Control, mesmo padrão tar-based do `.cpanel.yml` usado nos outros dois repos (rsync não disponível no host).
- Sem variáveis de ambiente sensíveis no build (API URL pode ser hardcoded para `https://api.oruidoscomputadores.pt`, tal como o site público faz hoje).

## Testes

- Backend: Pest feature tests para cada endpoint novo (lista vazia, lista com dados, filtros, scoping por técnico, 403 quando técnico tenta ver ticket alheio, `atribuir` endpoint com user não-técnico rejeitado).
- Frontend: sem suite de testes formal nesta fase (mesmo padrão do site público, que também não tem testes frontend) — verificação manual em browser antes de fechar.
