# Fluxo Técnico + Tracking Público Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Backend (`rui-tech-helper-api`) support for the new Repair-Labs-style ticket flow: renamed estado vocabulary, per-ticket issues (Fixed/Can't Fix), lockable diagnostic checklist per categoria, and public no-login tracking via `tracking_token`.

**Architecture:** Laravel 11 API. Keep `TicketEstado` PHP enum **case names** unchanged (`Aberto`, `EmAnalise`, ...) and only remap their `->value` strings, so the ~30 existing tests that reference `TicketEstado::Aberto` etc. keep compiling untouched — only the handful of places that hardcode the old *string* literals need edits. New `ticket_issues` and `ticket_checklist_respostas` tables follow the existing `ticket_eventos` pattern (belongsTo Ticket, FK to `users` for who acted). Checklist item text lives in `config/checklists.php` (fixed, not DB-driven) — only completion state is persisted. Public routes reuse the same "unauthenticated group, ownership by token" pattern already used by `Public\ConviteController`.

**Tech Stack:** Laravel 11, Pest (Feature tests, `RefreshDatabase` via base TestCase — same convention as existing `tests/Feature/*`), MySQL in prod / whatever driver `phpunit.xml` configures for tests.

**Scope of this plan:** backend only (`rui-tech-helper-api`). CRM frontend (`ticket-detail.tsx` rewrite) and the new `rui-tech-helper-tracking` repo are separate plans, written after this one ships and its endpoints exist to build against — they're independent codebases/build systems and shouldn't share one plan per the Scope Check in `writing-plans`.

---

### Task 1: Remap `TicketEstado` values + migrate existing data

**Files:**
- Modify: `app/Enums/TicketEstado.php`
- Modify: `app/Http/Controllers/Tickets/TicketController.php:64` (validation `in:` list)
- Create: `database/migrations/2026_08_25_100000_remap_ticket_estado_values.php`
- Modify: `tests/Feature/TicketEstadoEndpointTest.php` (literal `em_analise`/`em_curso` → new values)
- Modify: `tests/Feature/Admin/TicketIndexEndpointTest.php` (literal `resolvido` → `entregue`)
- Modify: `tests/Feature/Admin/DashboardEndpointTest.php` (literal `por_estado.resolvido` → `por_estado.entregue`)

Mapping (case name unchanged, value changes):

| Case | Old value | New value |
|---|---|---|
| `Aberto` | `aberto` | `recebido` |
| `EmAnalise` | `em_analise` | `em_diagnostico` |
| `EmCurso` | `em_curso` | `em_reparacao` |
| `AguardaCliente` | `aguarda_cliente` | `pronto_levantamento` |
| `AguardaPeca` | `aguarda_peca` | `aguarda_pecas` |
| `EmTestes` | `em_testes` | `reparacao_concluida` |
| `Resolvido` | `resolvido` | `entregue` |
| `Cancelado` | `cancelado` | `cancelado` |

- [ ] **Step 1: Update the enum values**

```php
<?php
// app/Enums/TicketEstado.php
namespace App\Enums;

enum TicketEstado: string
{
    case Aberto = 'recebido';
    case EmAnalise = 'em_diagnostico';
    case EmCurso = 'em_reparacao';
    case AguardaCliente = 'pronto_levantamento';
    case AguardaPeca = 'aguarda_pecas';
    case EmTestes = 'reparacao_concluida';
    case Resolvido = 'entregue';
    case Cancelado = 'cancelado';
}
```

- [ ] **Step 2: Update the validation `in:` list in `TicketController::updateEstado`**

In `app/Http/Controllers/Tickets/TicketController.php`, line 64, replace:

```php
            'estado' => ['required', 'in:aberto,em_analise,em_curso,aguarda_cliente,aguarda_peca,em_testes,resolvido,cancelado'],
```

with:

```php
            'estado' => ['required', 'in:recebido,em_diagnostico,em_reparacao,pronto_levantamento,aguarda_pecas,reparacao_concluida,entregue,cancelado'],
```

- [ ] **Step 3: Write the data migration**

Both `tickets.estado` and `ticket_eventos.estado_anterior`/`estado_novo` store the raw string value — existing prod rows have the old strings, so they must be remapped or `TicketEstado::from()` will throw on read.

```php
<?php
// database/migrations/2026_08_25_100000_remap_ticket_estado_values.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $mapa = [
        'aberto' => 'recebido',
        'em_analise' => 'em_diagnostico',
        'em_curso' => 'em_reparacao',
        'aguarda_cliente' => 'pronto_levantamento',
        'aguarda_peca' => 'aguarda_pecas',
        'em_testes' => 'reparacao_concluida',
        'resolvido' => 'entregue',
    ];

    public function up(): void
    {
        foreach ($this->mapa as $antigo => $novo) {
            DB::table('tickets')->where('estado', $antigo)->update(['estado' => $novo]);
            DB::table('ticket_eventos')->where('estado_anterior', $antigo)->update(['estado_anterior' => $novo]);
            DB::table('ticket_eventos')->where('estado_novo', $antigo)->update(['estado_novo' => $novo]);
        }
    }

    public function down(): void
    {
        foreach (array_flip($this->mapa) as $novo => $antigo) {
            DB::table('tickets')->where('estado', $novo)->update(['estado' => $antigo]);
            DB::table('ticket_eventos')->where('estado_anterior', $novo)->update(['estado_anterior' => $antigo]);
            DB::table('ticket_eventos')->where('estado_novo', $novo)->update(['estado_novo' => $antigo]);
        }
    }
};
```

Note: the `tickets.estado` column default (`'aberto'`, set in the original `create_tickets_table` migration) is left as-is — `TicketController::store`/`storeCliente` always pass `estado` explicitly, so the stale default is never read. Not touched here to avoid a `doctrine/dbal` dependency for a no-op column-default change.

- [ ] **Step 4: Fix literal-string test assertions**

In `tests/Feature/TicketEstadoEndpointTest.php`:
- Line 39-40 (`'estado' => 'em_analise'` in the request body and `assertJsonPath('ticket.estado', 'em_analise')`) → change both `'em_analise'` to `'em_diagnostico'`.
- Line 52-53 (`'estado' => 'em_curso'` and its usage in the "tecnico atribuido" test) → change `'em_curso'` to `'em_reparacao'`.
- Line 64-66 (`'em_curso'` in the "tecnico nao atribuido" test) → change to `'em_reparacao'`.

In `tests/Feature/Admin/TicketIndexEndpointTest.php` line 49 and 53: change both `'resolvido'` literals (the query string `?estado=resolvido` and `assertJsonPath('data.0.estado', 'resolvido')`) to `'entregue'`.

In `tests/Feature/Admin/DashboardEndpointTest.php` line 45: change `assertJsonPath('por_estado.resolvido', 1)` to `assertJsonPath('por_estado.entregue', 1)`.

- [ ] **Step 5: Run the full suite to confirm nothing else broke**

Run: `php artisan test`
Expected: all tests pass (the enum case names didn't change, so the ~30 files referencing `TicketEstado::Aberto` etc. compile and pass unchanged; only the literal-string tests touched above needed edits).

- [ ] **Step 6: Commit**

```bash
git add app/Enums/TicketEstado.php app/Http/Controllers/Tickets/TicketController.php database/migrations/2026_08_25_100000_remap_ticket_estado_values.php tests/Feature/TicketEstadoEndpointTest.php tests/Feature/Admin/TicketIndexEndpointTest.php tests/Feature/Admin/DashboardEndpointTest.php
git commit -m "feat: remap TicketEstado to Repair-Labs-style vocabulary"
```

---

### Task 2: `ticket_issues` table + model

**Files:**
- Create: `database/migrations/2026_08_25_100100_create_ticket_issues_table.php`
- Create: `app/Models/TicketIssue.php`
- Modify: `app/Models/Ticket.php` (add `issues()` relation)
- Test: `tests/Feature/TicketIssueModelTest.php`

- [ ] **Step 1: Write the failing model test**

```php
<?php
// tests/Feature/TicketIssueModelTest.php
use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\TicketIssue;
use App\Models\User;

function criarTicketParaIssue(): Ticket
{
    $clienteUser = User::factory()->create(['role' => 'cliente']);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Issue',
        'email' => $clienteUser->email,
        'telefone' => '912345678',
    ]);

    return Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Nao arranca.',
    ]);
}

test('ticket tem issues relacionadas, resultado por omissao pendente', function () {
    $ticket = criarTicketParaIssue();

    $issue = $ticket->issues()->create(['descricao' => 'Fonte de alimentacao morta']);

    expect($issue->resultado)->toBe('pendente');
    expect($ticket->issues)->toHaveCount(1);
});

test('issue guarda quem resolveu e quando', function () {
    $ticket = criarTicketParaIssue();
    $tecnico = User::factory()->create(['role' => 'tecnico']);

    $issue = $ticket->issues()->create(['descricao' => 'Fonte de alimentacao morta']);
    $issue->update([
        'resultado' => 'resolvido',
        'resolvido_por_user_id' => $tecnico->id,
        'resolvido_at' => now(),
    ]);

    expect($issue->fresh()->resultado)->toBe('resolvido');
    expect($issue->fresh()->resolvido_por_user_id)->toBe($tecnico->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TicketIssueModelTest`
Expected: FAIL — table `ticket_issues` doesn't exist / `Ticket::issues()` undefined.

- [ ] **Step 3: Write the migration**

```php
<?php
// database/migrations/2026_08_25_100100_create_ticket_issues_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets');
            $table->text('descricao');
            $table->string('resultado')->default('pendente');
            $table->text('observacao')->nullable();
            $table->foreignId('resolvido_por_user_id')->nullable()->constrained('users');
            $table->timestamp('resolvido_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_issues');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php
// app/Models/TicketIssue.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketIssue extends Model
{
    protected $fillable = [
        'ticket_id',
        'descricao',
        'resultado',
        'observacao',
        'resolvido_por_user_id',
        'resolvido_at',
    ];

    protected function casts(): array
    {
        return [
            'resolvido_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function resolvidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolvido_por_user_id');
    }
}
```

- [ ] **Step 5: Add the `issues()` relation to `Ticket`**

In `app/Models/Ticket.php`, after the `equipamentoRegistos()` method (line 69), add:

```php

    public function issues(): HasMany
    {
        return $this->hasMany(TicketIssue::class);
    }
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=TicketIssueModelTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_25_100100_create_ticket_issues_table.php app/Models/TicketIssue.php app/Models/Ticket.php tests/Feature/TicketIssueModelTest.php
git commit -m "feat: add ticket_issues table and model"
```

---

### Task 3: Issue endpoints (admin + tecnico)

**Files:**
- Create: `app/Http/Controllers/Tickets/TicketIssueController.php`
- Modify: `routes/api.php` (add routes to `admin` and `tecnico` groups)
- Test: `tests/Feature/TicketIssueEndpointTest.php`

- [ ] **Step 1: Write the failing endpoint tests**

```php
<?php
// tests/Feature/TicketIssueEndpointTest.php
use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\User;

function criarTicketComTecnicoParaIssue(?User $tecnico = null): Ticket
{
    $clienteUser = User::factory()->create(['role' => 'cliente']);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Issue',
        'email' => $clienteUser->email,
        'telefone' => '912345678',
    ]);

    return Ticket::create([
        'cliente_id' => $cliente->id,
        'tecnico_id' => $tecnico?->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Nao arranca.',
    ]);
}

test('admin cria issue no ticket', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = criarTicketComTecnicoParaIssue();

    $response = $this->actingAs($admin)->postJson("/api/admin/tickets/{$ticket->id}/issues", [
        'descricao' => 'Fonte de alimentacao morta',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('issue.resultado', 'pendente');
});

test('tecnico atribuido marca issue como resolvida', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketComTecnicoParaIssue($tecnico);
    $issue = $ticket->issues()->create(['descricao' => 'Fonte morta']);

    $response = $this->actingAs($tecnico)->patchJson("/api/tecnico/tickets/{$ticket->id}/issues/{$issue->id}", [
        'resultado' => 'resolvido',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('issue.resultado', 'resolvido');
    $response->assertJsonPath('issue.resolvido_por_user_id', $tecnico->id);
});

test('tecnico nao atribuido nao pode marcar issue', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $outroTecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketComTecnicoParaIssue($outroTecnico);
    $issue = $ticket->issues()->create(['descricao' => 'Fonte morta']);

    $response = $this->actingAs($tecnico)->patchJson("/api/tecnico/tickets/{$ticket->id}/issues/{$issue->id}", [
        'resultado' => 'resolvido',
    ]);

    $response->assertStatus(403);
});

test('issue de outro ticket devolve 404', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ticketA = criarTicketComTecnicoParaIssue();
    $ticketB = criarTicketComTecnicoParaIssue();
    $issueDoTicketB = $ticketB->issues()->create(['descricao' => 'Outro problema']);

    $response = $this->actingAs($admin)->patchJson("/api/admin/tickets/{$ticketA->id}/issues/{$issueDoTicketB->id}", [
        'resultado' => 'resolvido',
    ]);

    $response->assertStatus(404);
});

test('resultado invalido falha validacao', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = criarTicketComTecnicoParaIssue();
    $issue = $ticket->issues()->create(['descricao' => 'Fonte morta']);

    $response = $this->actingAs($admin)->patchJson("/api/admin/tickets/{$ticket->id}/issues/{$issue->id}", [
        'resultado' => 'invalido',
    ]);

    $response->assertStatus(422);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=TicketIssueEndpointTest`
Expected: FAIL with 404 (routes don't exist yet).

- [ ] **Step 3: Write the controller**

```php
<?php
// app/Http/Controllers/Tickets/TicketIssueController.php
namespace App\Http\Controllers\Tickets;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketIssue;
use Illuminate\Http\Request;

class TicketIssueController extends Controller
{
    public function store(Request $request, Ticket $ticket)
    {
        if ($request->user()->role === UserRole::Tecnico) {
            abort_if($ticket->tecnico_id !== $request->user()->id, 403);
        }

        $data = $request->validate([
            'descricao' => ['required', 'string'],
        ]);

        $issue = $ticket->issues()->create($data);

        return response()->json(['issue' => $issue], 201);
    }

    public function update(Request $request, Ticket $ticket, TicketIssue $issue)
    {
        if ($request->user()->role === UserRole::Tecnico) {
            abort_if($ticket->tecnico_id !== $request->user()->id, 403);
        }

        abort_if($issue->ticket_id !== $ticket->id, 404);

        $data = $request->validate([
            'resultado' => ['required', 'in:resolvido,nao_resolvido'],
            'observacao' => ['nullable', 'string'],
        ]);

        $issue->update([
            ...$data,
            'resolvido_por_user_id' => $request->user()->id,
            'resolvido_at' => now(),
        ]);

        return response()->json(['issue' => $issue->fresh()]);
    }
}
```

- [ ] **Step 4: Add routes**

In `routes/api.php`, inside the `admin` group (after line 37, the `anexos` route):

```php
    Route::post('/tickets/{ticket}/issues', [\App\Http\Controllers\Tickets\TicketIssueController::class, 'store']);
    Route::patch('/tickets/{ticket}/issues/{issue}', [\App\Http\Controllers\Tickets\TicketIssueController::class, 'update']);
```

Inside the `tecnico` group (after line 48, the `anexos` route):

```php
    Route::post('/tickets/{ticket}/issues', [\App\Http\Controllers\Tickets\TicketIssueController::class, 'store']);
    Route::patch('/tickets/{ticket}/issues/{issue}', [\App\Http\Controllers\Tickets\TicketIssueController::class, 'update']);
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=TicketIssueEndpointTest`
Expected: PASS (5/5)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Tickets/TicketIssueController.php routes/api.php tests/Feature/TicketIssueEndpointTest.php
git commit -m "feat: add ticket issue create/update endpoints"
```

---

### Task 4: Checklist config + `ticket_checklist_respostas` table + model

**Files:**
- Create: `config/checklists.php`
- Create: `database/migrations/2026_08_25_100200_create_ticket_checklist_respostas_table.php`
- Create: `app/Models/TicketChecklistResposta.php`
- Modify: `app/Models/Ticket.php` (add `checklistRespostas()` relation)
- Test: `tests/Feature/TicketChecklistModelTest.php`

- [ ] **Step 1: Write the failing model test**

```php
<?php
// tests/Feature/TicketChecklistModelTest.php
use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\User;

test('checklist fixa existe para cada categoria', function () {
    $checklists = config('checklists');

    expect($checklists)->toHaveKeys(['hardware', 'software', 'rede', 'backup']);
    expect($checklists['hardware'])->toContain('Testar fonte de alimentacao');
});

test('ticket tem respostas de checklist relacionadas', function () {
    $clienteUser = User::factory()->create(['role' => 'cliente']);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Checklist',
        'email' => $clienteUser->email,
        'telefone' => '912345678',
    ]);
    $ticket = Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Nao arranca.',
    ]);

    $resposta = $ticket->checklistRespostas()->create(['item_chave' => 'testar-fonte-de-alimentacao']);

    expect($resposta->concluido)->toBeFalse();
    expect($ticket->checklistRespostas)->toHaveCount(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TicketChecklistModelTest`
Expected: FAIL — `config('checklists')` empty, table/relation missing.

- [ ] **Step 3: Write the checklist config**

```php
<?php
// config/checklists.php
return [
    'hardware' => [
        'Testar fonte de alimentacao',
        'Verificar RAM',
        'Testar disco',
        'Verificar temperatura',
    ],
    'software' => [
        'Verificar antivirus',
        'Testar arranque limpo',
        'Verificar drivers',
    ],
    'rede' => [
        'Testar cabo/porta',
        'Verificar router/switch',
        'Testar velocidade',
    ],
    'backup' => [
        'Confirmar espaco disponivel',
        'Testar restauro',
        'Verificar agendamento',
    ],
];
```

- [ ] **Step 4: Write the migration**

```php
<?php
// database/migrations/2026_08_25_100200_create_ticket_checklist_respostas_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_checklist_respostas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets');
            $table->string('item_chave');
            $table->boolean('concluido')->default(false);
            $table->foreignId('concluido_por_user_id')->nullable()->constrained('users');
            $table->timestamp('concluido_at')->nullable();
            $table->timestamps();
            $table->unique(['ticket_id', 'item_chave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_checklist_respostas');
    }
};
```

- [ ] **Step 5: Write the model**

```php
<?php
// app/Models/TicketChecklistResposta.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketChecklistResposta extends Model
{
    protected $fillable = [
        'ticket_id',
        'item_chave',
        'concluido',
        'concluido_por_user_id',
        'concluido_at',
    ];

    protected function casts(): array
    {
        return [
            'concluido' => 'boolean',
            'concluido_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function concluidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'concluido_por_user_id');
    }
}
```

- [ ] **Step 6: Add the `checklistRespostas()` relation to `Ticket`**

In `app/Models/Ticket.php`, after the `issues()` method added in Task 2, add:

```php

    public function checklistRespostas(): HasMany
    {
        return $this->hasMany(TicketChecklistResposta::class);
    }
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=TicketChecklistModelTest`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add config/checklists.php database/migrations/2026_08_25_100200_create_ticket_checklist_respostas_table.php app/Models/TicketChecklistResposta.php app/Models/Ticket.php tests/Feature/TicketChecklistModelTest.php
git commit -m "feat: add fixed checklist config and ticket_checklist_respostas table"
```

---

### Task 5: Checklist toggle endpoint (admin + tecnico), with permanent lock

**Files:**
- Create: `app/Http/Controllers/Tickets/TicketChecklistController.php`
- Modify: `routes/api.php` (add routes to `admin` and `tecnico` groups)
- Test: `tests/Feature/TicketChecklistEndpointTest.php`

- [ ] **Step 1: Write the failing endpoint tests**

```php
<?php
// tests/Feature/TicketChecklistEndpointTest.php
use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\User;

function criarTicketHardwareParaChecklist(?User $tecnico = null): Ticket
{
    $clienteUser = User::factory()->create(['role' => 'cliente']);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Checklist',
        'email' => $clienteUser->email,
        'telefone' => '912345678',
    ]);

    return Ticket::create([
        'cliente_id' => $cliente->id,
        'tecnico_id' => $tecnico?->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Nao arranca.',
    ]);
}

test('admin marca item da checklist como concluido', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = criarTicketHardwareParaChecklist();

    $response = $this->actingAs($admin)->patchJson("/api/admin/tickets/{$ticket->id}/checklist/testar-fonte-de-alimentacao");

    $response->assertStatus(200);
    $response->assertJsonPath('resposta.concluido', true);
    $response->assertJsonPath('resposta.concluido_por_user_id', $admin->id);
});

test('marcar item ja concluido devolve 409', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = criarTicketHardwareParaChecklist();

    $this->actingAs($admin)->patchJson("/api/admin/tickets/{$ticket->id}/checklist/testar-fonte-de-alimentacao");
    $response = $this->actingAs($admin)->patchJson("/api/admin/tickets/{$ticket->id}/checklist/testar-fonte-de-alimentacao");

    $response->assertStatus(409);
});

test('item que nao existe na categoria devolve 404', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = criarTicketHardwareParaChecklist();

    $response = $this->actingAs($admin)->patchJson("/api/admin/tickets/{$ticket->id}/checklist/item-inexistente");

    $response->assertStatus(404);
});

test('tecnico nao atribuido nao pode marcar checklist', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $outroTecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketHardwareParaChecklist($outroTecnico);

    $response = $this->actingAs($tecnico)->patchJson("/api/tecnico/tickets/{$ticket->id}/checklist/testar-fonte-de-alimentacao");

    $response->assertStatus(403);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=TicketChecklistEndpointTest`
Expected: FAIL with 404 (routes don't exist yet).

- [ ] **Step 3: Write the controller**

```php
<?php
// app/Http/Controllers/Tickets/TicketChecklistController.php
namespace App\Http\Controllers\Tickets;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TicketChecklistController extends Controller
{
    public function toggle(Request $request, Ticket $ticket, string $itemChave)
    {
        if ($request->user()->role === UserRole::Tecnico) {
            abort_if($ticket->tecnico_id !== $request->user()->id, 403);
        }

        $itensCategoria = config('checklists.'.$ticket->categoria->value, []);
        $chavesValidas = collect($itensCategoria)->map(fn (string $item) => Str::slug($item));

        abort_unless($chavesValidas->contains($itemChave), 404, 'Item de checklist nao existe para esta categoria.');

        $resposta = $ticket->checklistRespostas()->firstOrCreate(['item_chave' => $itemChave]);

        abort_if($resposta->concluido, 409, 'Item ja foi marcado como concluido.');

        $resposta->update([
            'concluido' => true,
            'concluido_por_user_id' => $request->user()->id,
            'concluido_at' => now(),
        ]);

        return response()->json(['resposta' => $resposta->fresh()]);
    }
}
```

- [ ] **Step 4: Add routes**

In `routes/api.php`, inside the `admin` group (after the issues routes added in Task 3):

```php
    Route::patch('/tickets/{ticket}/checklist/{itemChave}', [\App\Http\Controllers\Tickets\TicketChecklistController::class, 'toggle']);
```

Inside the `tecnico` group (after the issues routes added in Task 3):

```php
    Route::patch('/tickets/{ticket}/checklist/{itemChave}', [\App\Http\Controllers\Tickets\TicketChecklistController::class, 'toggle']);
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=TicketChecklistEndpointTest`
Expected: PASS (4/4)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Tickets/TicketChecklistController.php routes/api.php tests/Feature/TicketChecklistEndpointTest.php
git commit -m "feat: add checklist toggle endpoint with permanent lock"
```

---

### Task 6: `tracking_token` column, auto-generated on ticket creation

**Files:**
- Create: `database/migrations/2026_08_25_100300_add_tracking_token_to_tickets_table.php`
- Modify: `app/Models/Ticket.php` (add `booted()` to generate the token)
- Test: `tests/Feature/TicketTrackingTokenTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/TicketTrackingTokenTest.php
use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\User;

test('ticket recebe tracking_token unico ao ser criado', function () {
    $clienteUser = User::factory()->create(['role' => 'cliente']);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Tracking',
        'email' => $clienteUser->email,
        'telefone' => '912345678',
    ]);

    $ticket = Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Nao arranca.',
    ]);

    expect($ticket->tracking_token)->not->toBeNull();
    expect(strlen($ticket->tracking_token))->toBe(36);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TicketTrackingTokenTest`
Expected: FAIL — column `tracking_token` doesn't exist.

- [ ] **Step 3: Write the migration**

```php
<?php
// database/migrations/2026_08_25_100300_add_tracking_token_to_tickets_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->uuid('tracking_token')->nullable()->unique()->after('id');
        });

        foreach (\DB::table('tickets')->whereNull('tracking_token')->pluck('id') as $id) {
            \DB::table('tickets')->where('id', $id)->update(['tracking_token' => (string) Str::uuid()]);
        }
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('tracking_token');
        });
    }
};
```

- [ ] **Step 4: Generate the token on creation in the model**

In `app/Models/Ticket.php`, add `'tracking_token'` to the `$fillable` array (after `'cliente_id'`, line 21), and add a `booted()` hook after the `casts()` method (after line 39):

```php

    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket) {
            $ticket->tracking_token ??= (string) \Illuminate\Support\Str::uuid();
        });
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=TicketTrackingTokenTest`
Expected: PASS

- [ ] **Step 6: Run the full suite** (ticket creation is exercised by dozens of existing tests — confirm the new auto-filled column doesn't break any of them)

Run: `php artisan test`
Expected: all pass.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_25_100300_add_tracking_token_to_tickets_table.php app/Models/Ticket.php tests/Feature/TicketTrackingTokenTest.php
git commit -m "feat: auto-generate tracking_token on ticket creation"
```

---

### Task 7: Public tracking `show` endpoint

**Files:**
- Create: `app/Http/Controllers/Public/TrackingController.php`
- Modify: `routes/api.php` (add public route group)
- Test: `tests/Feature/PublicTrackingShowTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/PublicTrackingShowTest.php
use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\User;

test('token valido devolve dados do ticket', function () {
    $clienteUser = User::factory()->create(['role' => 'cliente']);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Publico',
        'email' => $clienteUser->email,
        'telefone' => '912345678',
    ]);
    $ticket = Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Nao arranca.',
    ]);
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket->mudarEstado($admin, TicketEstado::EmAnalise, 'Nota visivel ao cliente', true);
    $ticket->mudarEstado($admin, TicketEstado::EmCurso, 'Nota interna', false);

    $response = $this->getJson("/api/public/tracking/{$ticket->tracking_token}");

    $response->assertStatus(200);
    $response->assertJsonPath('ticket.titulo', 'PC nao liga');
    $response->assertJsonPath('ticket.estado', 'em_reparacao');
    $response->assertJsonCount(2, 'ticket.eventos');
    $response->assertJsonPath('ticket.eventos.0.observacao', 'Nota visivel ao cliente');
    $response->assertJsonPath('ticket.eventos.1.observacao', null);
});

test('token invalido devolve 404', function () {
    $response = $this->getJson('/api/public/tracking/token-que-nao-existe');

    $response->assertStatus(404);
});

test('resposta inclui issues e progresso da checklist', function () {
    $clienteUser = User::factory()->create(['role' => 'cliente']);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Publico',
        'email' => $clienteUser->email,
        'telefone' => '912345678',
    ]);
    $ticket = Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Nao arranca.',
    ]);
    $ticket->issues()->create(['descricao' => 'Fonte morta']);
    $ticket->checklistRespostas()->create(['item_chave' => 'testar-fonte-de-alimentacao', 'concluido' => true, 'concluido_at' => now()]);

    $response = $this->getJson("/api/public/tracking/{$ticket->tracking_token}");

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'ticket.issues');
    $response->assertJsonPath('ticket.checklist.0.chave', 'testar-fonte-de-alimentacao');
    $response->assertJsonPath('ticket.checklist.0.concluido', true);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=PublicTrackingShowTest`
Expected: FAIL with 404 (route doesn't exist).

- [ ] **Step 3: Write the controller**

```php
<?php
// app/Http/Controllers/Public/TrackingController.php
namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Support\Str;

class TrackingController extends Controller
{
    public function show(string $token)
    {
        $ticket = Ticket::where('tracking_token', $token)->first();
        abort_if(! $ticket, 404);

        $eventos = $ticket->eventos()->orderBy('created_at')->get()->map(fn ($evento) => [
            'estado_anterior' => $evento->estado_anterior->value,
            'estado_novo' => $evento->estado_novo->value,
            'observacao' => $evento->observacao_visivel_cliente ? $evento->observacao : null,
            'created_at' => $evento->created_at,
        ]);

        $issues = $ticket->issues()->orderBy('created_at')->get()->map(fn ($issue) => [
            'descricao' => $issue->descricao,
            'resultado' => $issue->resultado,
        ]);

        $itensCategoria = config('checklists.'.$ticket->categoria->value, []);
        $respostas = $ticket->checklistRespostas()->get()->keyBy('item_chave');
        $checklist = collect($itensCategoria)->map(function (string $item) use ($respostas) {
            $chave = Str::slug($item);
            $resposta = $respostas->get($chave);

            return [
                'chave' => $chave,
                'texto' => $item,
                'concluido' => $resposta?->concluido ?? false,
                'concluido_at' => $resposta?->concluido_at,
            ];
        })->values();

        $orcamentos = $ticket->orcamentos()->with('itens')->orderBy('versao')->get()->map(fn ($orcamento) => [
            'id' => $orcamento->id,
            'versao' => $orcamento->versao,
            'estado' => $orcamento->estado->value,
            'created_at' => $orcamento->created_at,
            'decided_at' => $orcamento->decided_at,
            'itens' => $orcamento->itens->map(fn ($item) => [
                'descricao' => $item->descricao,
                'quantidade' => $item->quantidade,
                'preco_unitario' => $item->preco_unitario,
            ]),
            'total' => $orcamento->total(),
        ]);

        return response()->json(['ticket' => [
            'titulo' => $ticket->titulo,
            'descricao' => $ticket->descricao,
            'estado' => $ticket->estado->value,
            'categoria' => $ticket->categoria->value,
            'created_at' => $ticket->created_at,
            'eventos' => $eventos,
            'issues' => $issues,
            'checklist' => $checklist,
            'orcamentos' => $orcamentos,
        ]]);
    }
}
```

- [ ] **Step 4: Add the public route group**

In `routes/api.php`, after the `webhooks/ifthenpay` route (line 10), add:

```php
Route::prefix('public/tracking')->middleware('throttle:20,1')->group(function () {
    Route::get('/{token}', [\App\Http\Controllers\Public\TrackingController::class, 'show']);
});
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=PublicTrackingShowTest`
Expected: PASS (3/3)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Public/TrackingController.php routes/api.php tests/Feature/PublicTrackingShowTest.php
git commit -m "feat: add public tracking show endpoint"
```

---

### Task 8: Public tracking `decisaoOrcamento` endpoint

**Files:**
- Modify: `app/Http/Controllers/Public/TrackingController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/PublicTrackingDecisaoTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/PublicTrackingDecisaoTest.php
use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Orcamento;
use App\Models\Ticket;
use App\Models\User;

function criarTicketComOrcamentoPendente(?string $nif = '123456789'): array
{
    $clienteUser = User::factory()->create(['role' => 'cliente']);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Publico',
        'email' => $clienteUser->email,
        'telefone' => '912345678',
        'nif' => $nif,
    ]);
    $ticket = Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Nao arranca.',
    ]);
    $orcamento = Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => 'pendente']);
    $orcamento->itens()->create(['descricao' => 'Fonte nova', 'quantidade' => 1, 'preco_unitario' => 50]);

    return [$ticket, $orcamento];
}

test('cliente aprova orcamento pelo link publico com nif correto', function () {
    [$ticket, $orcamento] = criarTicketComOrcamentoPendente();

    $response = $this->postJson("/api/public/tracking/{$ticket->tracking_token}/orcamentos/{$orcamento->id}/decisao", [
        'decisao' => 'aprovado',
        'nif' => '123456789',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('orcamento.estado', 'aprovado');
});

test('nif errado devolve 422', function () {
    [$ticket, $orcamento] = criarTicketComOrcamentoPendente();

    $response = $this->postJson("/api/public/tracking/{$ticket->tracking_token}/orcamentos/{$orcamento->id}/decisao", [
        'decisao' => 'aprovado',
        'nif' => '999999999',
    ]);

    $response->assertStatus(422);
});

test('orcamento de outro ticket devolve 404', function () {
    [$ticketA] = criarTicketComOrcamentoPendente();
    [, $orcamentoB] = criarTicketComOrcamentoPendente();

    $response = $this->postJson("/api/public/tracking/{$ticketA->tracking_token}/orcamentos/{$orcamentoB->id}/decisao", [
        'decisao' => 'aprovado',
        'nif' => '123456789',
    ]);

    $response->assertStatus(404);
});

test('orcamento ja decidido devolve 409', function () {
    [$ticket, $orcamento] = criarTicketComOrcamentoPendente();

    $this->postJson("/api/public/tracking/{$ticket->tracking_token}/orcamentos/{$orcamento->id}/decisao", [
        'decisao' => 'rejeitado',
        'nif' => '123456789',
    ]);

    $response = $this->postJson("/api/public/tracking/{$ticket->tracking_token}/orcamentos/{$orcamento->id}/decisao", [
        'decisao' => 'aprovado',
        'nif' => '123456789',
    ]);

    $response->assertStatus(409);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=PublicTrackingDecisaoTest`
Expected: FAIL with 404 (route doesn't exist).

- [ ] **Step 3: Add the method to `TrackingController`**

Add these imports at the top of `app/Http/Controllers/Public/TrackingController.php` (after `use Illuminate\Support\Str;`):

```php
use App\Enums\PagamentoEstado;
use App\Models\Orcamento;
use App\Models\Pagamento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
```

Add this method to the class, after `show()`:

```php

    public function decisaoOrcamento(Request $request, string $token, Orcamento $orcamento)
    {
        $ticket = Ticket::where('tracking_token', $token)->first();
        abort_if(! $ticket || $orcamento->ticket_id !== $ticket->id, 404);

        $data = $request->validate([
            'decisao' => ['required', 'in:aprovado,rejeitado'],
            'nif' => ['required', 'digits:9'],
        ]);

        abort_if($ticket->cliente->nif !== $data['nif'], 422, 'NIF nao corresponde ao cliente deste ticket.');

        $orcamento = DB::transaction(function () use ($orcamento, $data) {
            $locked = Orcamento::whereKey($orcamento->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->estado->value !== 'pendente', 409, 'Orcamento ja foi decidido.');

            $locked->update([
                'estado' => $data['decisao'],
                'decided_at' => now(),
            ]);

            if ($data['decisao'] === 'aprovado') {
                Pagamento::create([
                    'orcamento_id' => $locked->id,
                    'estado' => PagamentoEstado::Pendente,
                    'valor' => $locked->fresh('itens')->total(),
                ]);
            }

            return $locked;
        });

        return response()->json(['orcamento' => $orcamento->fresh()]);
    }
```

- [ ] **Step 4: Add the route**

In `routes/api.php`, inside the `public/tracking` group added in Task 7:

```php
    Route::post('/{token}/orcamentos/{orcamento}/decisao', [\App\Http\Controllers\Public\TrackingController::class, 'decisaoOrcamento']);
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=PublicTrackingDecisaoTest`
Expected: PASS (4/4)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Public/TrackingController.php routes/api.php tests/Feature/PublicTrackingDecisaoTest.php
git commit -m "feat: add public tracking orcamento decision endpoint"
```

---

### Task 9: Full suite verification

**Files:** none (verification only)

- [ ] **Step 1: Run the entire test suite**

Run: `php artisan test`
Expected: all tests pass (existing ~140 + the new tests added across Tasks 1-8).

- [ ] **Step 2: If anything fails, fix and re-run**

Common failure mode to check: any test elsewhere in the suite that hardcodes an old `TicketEstado` value string (`'aberto'`, `'resolvido'`, etc.) not caught by the Task 1 grep — re-grep with `grep -rn "estado.*=.*'aberto'\|'resolvido'" tests/` if the suite fails on an unexpected file.

- [ ] **Step 3: No commit needed** — this task is verification only, nothing to stage.
