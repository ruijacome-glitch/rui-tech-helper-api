# Fluxo Técnico + Tracking Público — Design

**Sub-projecto 8** do rebuild do CRM "O Rui dos Computadores". Sequência: 7 (Nova Intervenção, done) → **8 (Fluxo Técnico + Tracking)** → 9 (Faturação — Spec 2, deferred) → 10 (Agendamentos, Equipamentos+Documentos, etc.).

Inspirado no exemplo "Repair Labs": stepper de progresso, issues com resultado Fixed/Can't Fix, checklist de diagnóstico com lock permanente, página de tracking pública sem login com aprovação de orçamento e partilha via WhatsApp.

## Goal

1. Substituir o fluxo de estados actual (8 estados genéricos) por um stepper linear tipo Repair Labs.
2. Registo estruturado de problemas por ticket (issues), resolvido ou não.
3. Checklist de diagnóstico fixa por categoria, lock permanente ao concluir (nome + timestamp).
4. Página pública de tracking sem login, link partilhável, cliente vê estado/issues/checklist e aprova/rejeita orçamento.

## Scope

**Dentro:** novo enum de estados, `ticket_issues`, checklist fixa por categoria com lock, `tracking_token` por ticket, endpoints públicos sem auth, reescrita de `ticket-detail.tsx`, novo repo `rui-tech-helper-tracking`.

**Fora:**
- Faturação completa (pagamentos parciais múltiplos, modal "Manage Payments") — Spec 2.
- Editor de checklist via UI — fixo em código.
- Portal de cliente com login no site Lovable — superado pelo link-token.
- Agendamentos/calendário — sub-projecto à parte.

## Architecture

1. **Backend (`rui-tech-helper-api`)** — novo enum `TicketEstado`, tabelas `ticket_issues` e `ticket_checklist_respostas`, coluna `tickets.tracking_token`, rotas públicas `prefix('public/tracking')` sem `auth:sanctum`, autenticadas só pelo token na URL — mesmo padrão que `POST /convites/{token}/completar` já usa no projecto (rota pública existente, sem middleware auth).
2. **CRM (`rui-tech-helper-crm`)** — `ticket-detail.tsx`: stepper novos estados, issues, checklist, mantém orçamentos/anexos/equipamento.
3. **Tracking público (novo repo)** — SPA React+Vite sem auth, rota única `/t/:token`, endpoints públicos, stepper read-only + issues read-only + checklist read-only + orçamento pendente (aprovar/rejeitar com NIF) + botão WhatsApp (`wa.me`).

## Data model

### `TicketEstado` (substitui enum actual em `app/Enums/TicketEstado.php`)

```php
enum TicketEstado: string
{
    case Recebido = 'recebido';
    case EmDiagnostico = 'em_diagnostico';
    case AguardaPecas = 'aguarda_pecas';
    case EmReparacao = 'em_reparacao';
    case ReparacaoConcluida = 'reparacao_concluida';
    case ProntoLevantamento = 'pronto_levantamento';
    case Entregue = 'entregue';
    case Cancelado = 'cancelado';
}
```

Migration mapeia dados existentes (`tickets.estado` é `string`, sem FK):

| Antigo | Novo |
|---|---|
| aberto | recebido |
| em_analise | em_diagnostico |
| em_curso | em_reparacao |
| aguarda_cliente | pronto_levantamento |
| aguarda_peca | aguarda_pecas |
| em_testes | reparacao_concluida |
| resolvido | entregue |
| cancelado | cancelado |

`Ticket::mudarEstado()` mantém assinatura — só o enum muda.

### `ticket_issues` (nova tabela)

```php
Schema::create('ticket_issues', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ticket_id')->constrained('tickets');
    $table->text('descricao');
    $table->enum('resultado', ['pendente', 'resolvido', 'nao_resolvido'])->default('pendente');
    $table->text('observacao')->nullable();
    $table->foreignId('resolvido_por_user_id')->nullable()->constrained('users');
    $table->timestamp('resolvido_at')->nullable();
    $table->timestamps();
});
```

Criado/gerido só por admin/técnico: `POST /api/{admin,tecnico}/tickets/{ticket}/issues`, `PATCH .../issues/{issue}` (segue exactamente o padrão já usado em `routes/api.php` para `equipamento`/`anexos` dentro dos grupos `admin`/`tecnico`). Cliente vê read-only via tracking público. `resolvido_por_user_id`/`resolvido_at` preenchidos quando `resultado` sai de `pendente`.

### Checklist fixa por categoria

Itens fixos em array PHP (`config/checklists.php`), chave = `TicketCategoria` (`hardware, software, rede, backup`):

```php
'hardware' => ['Testar fonte de alimentação', 'Verificar RAM', 'Testar disco', 'Verificar temperatura'],
'software' => ['Verificar antivírus', 'Testar arranque limpo', 'Verificar drivers'],
'rede' => ['Testar cabo/porta', 'Verificar router/switch', 'Testar velocidade'],
'backup' => ['Confirmar espaço disponível', 'Testar restauro', 'Verificar agendamento'],
```

Tabela guarda só o estado por ticket:

```php
Schema::create('ticket_checklist_respostas', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ticket_id')->constrained('tickets');
    $table->string('item_chave'); // slug, ex. "testar-fonte-alimentacao"
    $table->boolean('concluido')->default(false);
    $table->foreignId('concluido_por_user_id')->nullable()->constrained('users');
    $table->timestamp('concluido_at')->nullable();
    $table->unique(['ticket_id', 'item_chave']);
});
```

Lock: `concluido = true` já gravado → `PATCH /api/{admin,tecnico}/tickets/{ticket}/checklist/{item_chave}` no mesmo item devolve `409`. Nunca desmarca. Primeiro PATCH cria a linha se não existir.

### `tickets.tracking_token`

```php
$table->uuid('tracking_token')->unique();
```

Gerado em `Ticket::creating()` (model event). Nunca exposto nos endpoints admin/técnico existentes, só usado para construir o link a partilhar.

## Backend — rotas públicas novas

```php
Route::prefix('public/tracking')->middleware('throttle:20,1')->group(function () {
    Route::get('/{token}', [Public\TrackingController::class, 'show']);
    Route::post('/{token}/orcamentos/{orcamento}/decisao', [Public\TrackingController::class, 'decisaoOrcamento']);
});
```

Segue o mesmo padrão dos grupos públicos já em `routes/api.php` (`Public\ConviteController`, `Public\ContactoController`, ambos sem `auth:sanctum`, `ContactoController` já usa `throttle:5,1`).

- `show`: resolve ticket por `tracking_token` (404 se não existir), devolve estado actual, categoria, eventos com `observacao_visivel_cliente = true` apenas, issues, checklist (progresso, sem acção), orçamento(s), pagamento(s). Reaproveita a serialização já usada em `TicketController::show` (rota `cliente`), filtrada ao que o cliente já vê hoje.
- `decisaoOrcamento`: mesma lógica de `OrcamentoController::decisao` (rota `cliente/orcamentos/{orcamento}/decisao`), troca validação de posse: em vez de "utilizador autenticado é o cliente do ticket", passa a ser "token da URL corresponde ao `ticket_id` do orçamento". Continua a exigir NIF do cliente no body (mesma regra actual) — compensa ausência de login.

`throttle:20,1` por IP mitiga enumeração; token é UUID v4 (122 bits), não sequencial.

## CRM — `ticket-detail.tsx`

- **Stepper**: 8 estados novos, componente visual horizontal (concluído/actual/futuro) em vez do `<select>` actual. Clique num passo futuro dispara `mudarEstado` como hoje.
- **Issues**: lista + "+ Adicionar issue" (admin/técnico), cada issue com badge de resultado + botões "Marcar resolvido"/"Não resolvido" quando pendente.
- **Checklist**: itens fixos da categoria do ticket, checkbox — ao marcar, confirma (é permanente), depois mostra nome+data, disabled.
- **Link de tracking**: bloco com URL completo (`https://tracking.oruidoscomputadores.pt/t/{token}`) + botão copiar + botão "Partilhar via WhatsApp" (`wa.me/?text=...`).

## Novo repo `rui-tech-helper-tracking`

- Stack igual ao CRM (React+Vite+TanStack Query), sem router multi-rota (só `/t/:token`), sem Sanctum/auth.
- Deploy: subdomínio `tracking.oruidoscomputadores.pt`, cPanel, build estático via mesmo padrão do CRM (`.cpanel.yml` + Git Version Control pull).
- UI reaproveita tokens visuais do CRM (`panel-tech`, cores, tipografia) para consistência de marca — projecto isolado, zero dependência de código do CRM.
- Nota implementação: superfície nova virada para clientes finais → fase de plano/código desta peça deve invocar `ui-ux-pro-max` para decisões de layout/paleta (regra global do utilizador) — não decidido nesta spec, decidir na fase de plano.

## Data flow

```
Técnico avança ticket no CRM → mudarEstado() → TicketEvento criado → email ao cliente (já existente)
Cliente recebe email OU link WhatsApp do técnico → abre tracking.oruidoscomputadores.pt/t/{token}
  → GET /api/public/tracking/{token} → mostra stepper actual + issues + checklist (read-only) + orçamento pendente
  → Cliente aprova/rejeita orçamento (com NIF) → POST .../decisao → mesma lógica de hoje, sem login
Técnico, no CRM, cria issues durante diagnóstico → marca resolvido/não resolvido conforme avança
Técnico marca itens da checklist → lock permanente após concluído
```

## Error handling

- Token inválido/inexistente → `404` genérico na página pública (não distingue "não existe" de "apagado").
- Checklist: alterar item já `concluido` → `409`, frontend ignora (botão já vem disabled — rede de segurança contra corrida).
- Decisão de orçamento com NIF errado → `422` (já existe hoje), mensagem inline.
- Sem throttle extra além de `throttle:20,1` — token errado devolve 404 simples, sem vazar dados de outros clientes.

## Testing

- Pest: migration de mapeamento de estados, `TicketIssue` CRUD + transições, checklist lock (segundo PATCH ao mesmo item → 409), rotas públicas (200 token válido, 404 inválido, decisão de orçamento sem Sanctum, falha sem NIF correcto).
- CRM/tracking frontend: sem test runner configurado (consistente com resto do projecto) — verificação manual via browser (Playwright/superpowers-chrome): avançar estado, criar+resolver issue, marcar checklist e confirmar lock, abrir link tracking sem sessão, aprovar orçamento a partir da página pública.

## Out of scope (explícito)

- Faturação/pagamentos parciais múltiplos, modal "Manage Payments" — Spec 2.
- Fotos de conclusão anexadas pelo cliente na página pública — fora do scope aprovado.
- Notificações WhatsApp automáticas (Business API) — só botão manual `wa.me`, sem envio automático.
- Edição de templates de checklist via UI admin — fixos em código.
