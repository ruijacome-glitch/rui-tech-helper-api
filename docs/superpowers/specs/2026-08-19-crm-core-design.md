# CRM core — design

Data: 2026-08-19
Sub-projecto 2 de 5 (fundação → **CRM core** → financeiro → conteúdo site → frontend SPA).

## Contexto

Sub-projecto 1 (fundação backend) está feito e mergeado em `master`: Laravel API, Sanctum SPA auth, roles admin/tecnico/cliente, `clientes`+`convites`, deploy cPanel. Este documento cobre o núcleo do CRM: tickets/intervenções, orçamentos com peças, e registo de levantamento/devolução de equipamento com assinatura. Ficha de diagnóstico fica fora de âmbito — sub-projecto futuro, o schema só deixa espaço (FK `ticket_id`).

Continua sem SSH no servidor — mesmas restrições de deploy do sub-projecto 1 (`vendor/` comitado, migrations correm localmente contra MySQL remoto, `.cpanel.yml` já corrigido para deploy fora de `public_html`).

## Modelo de dados

```
tickets
  id, cliente_id (FK clientes), tecnico_id (FK users, nullable — atribuição manual pelo admin),
  categoria enum(hardware|software|rede|backup),
  prioridade enum(urgente|normal|baixa),
  estado enum(aberto|em_analise|em_curso|aguarda_cliente|aguarda_peca|em_testes|resolvido|cancelado),
  origem enum(admin|cliente),
  titulo, descricao, timestamps

ticket_eventos   -- histórico de mudança de estado
  id, ticket_id, user_id (quem mudou), estado_anterior, estado_novo,
  observacao (nullable text), observacao_visivel_cliente (bool, default false), created_at

ticket_anexos
  id, ticket_id, user_id, path, nome_original, content_type, size, created_at

orcamentos       -- versionado
  id, ticket_id, versao (int, incremental por ticket), estado enum(pendente|aprovado|rejeitado),
  created_at, decided_at (nullable)

orcamento_itens
  id, orcamento_id, descricao, quantidade, preco_unitario

equipamento_registos   -- levantamento E devolução, mesmo modelo, campo `tipo` distingue
  id, ticket_id, tipo enum(entrega|devolucao), user_id (técnico que registou),
  nome_assinante, assinatura_path (PNG guardado em storage/app), observacoes (nullable), created_at
```

Sem controlo de stock/inventário — `orcamento_itens.descricao` é texto livre, sem ligação a catálogo de peças (YAGNI, sem armazém a gerir nesta fase).

## Fluxos

### Ticket — criação e ciclo de vida
- **Admin** cria ticket, atribui `tecnico_id` manualmente (sem auto-assign).
- **Cliente** pede novo serviço via portal → ticket nasce directo em `estado=aberto`, `origem=cliente`, sem técnico atribuído, sem passo de aprovação extra.
- Mudança de estado (por admin ou técnico atribuído) grava `ticket_eventos` — o par estado_anterior/estado_novo é **sempre** visível ao cliente na timeline; a `observacao` só aparece ao cliente se `observacao_visivel_cliente=true`, senão fica só interna.
- Toda mudança de estado dispara email Resend ao cliente (nome do ticket + novo estado + observação, se visível).

### Orçamento — criação, aprovação, versionamento
- Técnico ou admin cria `orcamentos` (v1) com uma ou mais `orcamento_itens` (descrição + quantidade + preço unitário) para um ticket.
- Ao criar, email Resend ao cliente: "orçamento pronto para aprovação", com link para o portal.
- Cliente vê lista de itens + total no portal, aprova ou rejeita o orçamento **completo** (sem aprovação item-a-item).
- Se aprovado: `estado=aprovado`, `decided_at` preenchido. Fim do fluxo dessa versão.
- Se rejeitado: `estado=rejeitado`, `decided_at` preenchido. Técnico/admin pode criar nova versão (`versao+1`) com itens ajustados — repete o ciclo. Sem limite de versões, histórico completo preservado (nenhuma versão é apagada/editada in-place).

### Levantamento / devolução de equipamento
- Técnico abre o ticket no CRM (tablet/telemóvel), regista `equipamento_registos` com `tipo=entrega` (levantamento inicial) ou `tipo=devolucao` (entrega final ao cliente).
- Cliente assina num canvas HTML no próprio ecrã do dispositivo do técnico; a imagem é convertida (dataURL → PNG) e enviada para `assinatura_path`.
- Mesmo endpoint/formulário serve os dois casos — só o campo `tipo` muda. Cada ticket pode ter no máximo um registo de cada `tipo` (validação de aplicação, não constraint de BD, para permitir correcção manual via admin se necessário).

## Permissões (role middleware, já existente)

- `role:admin` — CRUD completo em tickets, orçamentos, atribuições.
- `role:tecnico` — vê/gere tickets onde `tecnico_id = auth user`; cria orçamentos e registos de equipamento nesses tickets; não pode reatribuir ticket a outro técnico.
- `role:cliente` — vê apenas os seus próprios tickets (via `cliente_id` ligado ao seu `user_id`); vê timeline (estados + observações visíveis) e anexos; aprova/rejeita orçamentos; não edita nada directamente.

## Erros e testes

Mesmo padrão do sub-projecto 1: 422 validação, 401/403 auth/role, 404 recurso inexistente. Pest/PHPUnit cobrindo:
- Criação de ticket por admin e por cliente (origem correcta, estado inicial correcto).
- Middleware de role em cada endpoint novo (técnico só vê os seus, cliente só os seus).
- Mudança de estado grava evento correctamente, respeita `observacao_visivel_cliente`, dispara `Mail::fake()`-testável.
- Fluxo de orçamento: criação, aprovação, rejeição, criação de nova versão após rejeição, total calculado a partir dos itens.
- Registo de equipamento: upload de assinatura, distinção `tipo`, um registo por tipo por ticket.

## Fora de âmbito (sub-projectos seguintes / fases futuras)

- Ficha de diagnóstico (schema preparado via FK, construção fica para depois).
- Financeiro: ligação de orçamento aprovado a pagamento IfthenPay, faturação Moloni — sub-projecto 3.
- Controlo de stock/inventário de peças.
- Auto-atribuição de técnico a tickets.
- SLA/prazos por prioridade.
