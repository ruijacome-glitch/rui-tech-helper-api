# Clientes + Dashboard (sub-projecto 6) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a Clientes module (list + detail with 7 tabs) and a real KPI Dashboard to the CRM, replacing the current 3-page (tickets/pagamentos/login) frontend with the mockup's full navigation shell.

**Architecture:** Backend adds 3 read-only endpoints under the existing `admin` route group (`GET /clientes`, `GET /clientes/{cliente}`, `GET /dashboard`), reusing the existing Sanctum + `role:admin` middleware stack and Eloquent models (`Cliente`, `Ticket`, `Orcamento`, `Pagamento`) with one new relation (`Cliente::tickets()`). Frontend adds 3 real pages (`DashboardPage`, `ClientesListPage`, `ClienteDetailPage`) plus a generic `PlaceholderPage` for the mockup's not-yet-built modules, and extracts the `TableSkeleton`/`EmptyState` duplicated in `tickets-list.tsx`/`pagamentos-list.tsx` into a shared module.

**Tech Stack:** Laravel 11 + Pest (backend), React + Vite + TanStack Router + TanStack Query + Tailwind (frontend).

**Spec:** `docs/superpowers/specs/2026-08-22-clientes-dashboard-design.md`

---

## Task 1: `Cliente::tickets()` relation

**Files:**
- Modify: `app/Models/Cliente.php`
- Test: `tests/Feature/ClienteTicketsRelationTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\User;

test('cliente tem muitos tickets', function () {
    $user = User::factory()->create(['role' => 'cliente']);
    $cliente = Cliente::create([
        'user_id' => $user->id,
        'nome' => 'Cliente Relacao',
        'email' => 'cliente-relacao@example.com',
        'telefone' => '911111111',
    ]);

    Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'Ticket 1',
        'descricao' => 'Descricao.',
    ]);

    expect($cliente->tickets()->count())->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter="cliente tem muitos tickets"`
Expected: FAIL — `Call to undefined method App\Models\Cliente::tickets()`

- [ ] **Step 3: Add the relation**

In `app/Models/Cliente.php`, add the `HasMany` import and the `tickets()` method:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $fillable = ['user_id', 'nome', 'telefone', 'email', 'morada', 'nif', 'notas'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter="cliente tem muitos tickets"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Models/Cliente.php tests/Feature/ClienteTicketsRelationTest.php
git commit -m "feat: add Cliente::tickets relation"
```

---

## Task 2: `GET /api/admin/clientes` (list + search)

**Files:**
- Modify: `app/Http/Controllers/Admin/ClienteController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Admin/ClienteIndexEndpointTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Cliente;
use App\Models\User;

test('admin lista clientes paginados com contagem de intervencoes', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Cliente::create(['nome' => 'Ana Silva', 'email' => 'ana@example.com', 'telefone' => '911000001']);
    Cliente::create(['nome' => 'Bruno Costa', 'email' => 'bruno@example.com', 'telefone' => '911000002']);

    $response = $this->actingAs($admin)->getJson('/api/admin/clientes');

    $response->assertOk();
    $response->assertJsonCount(2, 'data');
    $response->assertJsonStructure(['data' => [['id', 'nome', 'email', 'telefone', 'created_at', 'intervencoes_count']], 'meta']);
});

test('admin pesquisa clientes por nome', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Cliente::create(['nome' => 'Ana Silva', 'email' => 'ana@example.com', 'telefone' => '911000001']);
    Cliente::create(['nome' => 'Bruno Costa', 'email' => 'bruno@example.com', 'telefone' => '911000002']);

    $response = $this->actingAs($admin)->getJson('/api/admin/clientes?search=ana');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.nome', 'Ana Silva');
});

test('tecnico nao pode listar clientes admin', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);

    $response = $this->actingAs($tecnico)->getJson('/api/admin/clientes');

    $response->assertStatus(403);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ClienteIndexEndpointTest`
Expected: FAIL — route `/api/admin/clientes` (GET) not defined (404)

- [ ] **Step 3: Add `index()` to `ClienteController` and wire the route**

`app/Http/Controllers/Admin/ClienteController.php` — add `index()` alongside the existing `store()`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ConviteCliente;
use App\Models\Cliente;
use App\Models\Convite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $clientes = Cliente::query()
            ->withCount('tickets')
            ->when($data['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nome', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('telefone', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $clientes->getCollection()->map(fn (Cliente $cliente) => [
                'id' => $cliente->id,
                'nome' => $cliente->nome,
                'email' => $cliente->email,
                'telefone' => $cliente->telefone,
                'created_at' => $cliente->created_at,
                'intervencoes_count' => $cliente->tickets_count,
            ]),
            'meta' => [
                'current_page' => $clientes->currentPage(),
                'last_page' => $clientes->lastPage(),
                'total' => $clientes->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $cliente = Cliente::create($data);

        $plaintextToken = Str::random(64);
        $convite = Convite::create([
            'cliente_id' => $cliente->id,
            'token_hash' => hash('sha256', $plaintextToken),
            'expires_at' => now()->addDays(7),
        ]);

        if ($cliente->email) {
            Mail::to($cliente->email)->send(new ConviteCliente($convite, $plaintextToken));
        }

        return response()->json(['cliente' => $cliente], 201);
    }
}
```

In `routes/api.php`, inside the existing `admin` group, add the route right after the `POST /clientes` line:

```php
    Route::post('/clientes', [\App\Http\Controllers\Admin\ClienteController::class, 'store']);
    Route::get('/clientes', [\App\Http\Controllers\Admin\ClienteController::class, 'index']);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ClienteIndexEndpointTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/ClienteController.php routes/api.php tests/Feature/Admin/ClienteIndexEndpointTest.php
git commit -m "feat: add GET /api/admin/clientes list endpoint with search"
```

---

## Task 3: `GET /api/admin/clientes/{cliente}` (detail)

**Files:**
- Modify: `app/Http/Controllers/Admin/ClienteController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Admin/ClienteShowEndpointTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Orcamento;
use App\Models\Pagamento;
use App\Models\Ticket;
use App\Models\User;

function criarClienteComTicketsParaShow(): Cliente
{
    return Cliente::create([
        'nome' => 'Carla Dias',
        'email' => 'carla@example.com',
        'telefone' => '911000003',
        'morada' => 'Rua A, 1',
        'nif' => '123456789',
    ]);
}

test('admin ve detalhe de cliente com resumo, intervencoes e orcamentos', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $cliente = criarClienteComTicketsParaShow();

    $ticket = Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Resolvido,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'Ticket A',
        'descricao' => 'Descricao.',
    ]);

    $orcamento = Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => 'aprovado', 'decided_at' => now()]);
    Pagamento::create(['orcamento_id' => $orcamento->id, 'estado' => 'pago', 'valor' => 75.00, 'paid_at' => now()]);

    $response = $this->actingAs($admin)->getJson("/api/admin/clientes/{$cliente->id}");

    $response->assertOk();
    $response->assertJsonPath('cliente.nome', 'Carla Dias');
    $response->assertJsonPath('resumo.intervencoes_total', 1);
    $response->assertJsonPath('resumo.faturacao_total', '75.00');
    $response->assertJsonCount(1, 'intervencoes');
    $response->assertJsonCount(1, 'orcamentos');
});

test('detalhe de cliente inexistente devolve 404', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->getJson('/api/admin/clientes/999999');

    $response->assertStatus(404);
});

test('tecnico nao pode ver detalhe de cliente admin', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $cliente = criarClienteComTicketsParaShow();

    $response = $this->actingAs($tecnico)->getJson("/api/admin/clientes/{$cliente->id}");

    $response->assertStatus(403);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ClienteShowEndpointTest`
Expected: FAIL — route not defined (404 even for the "exists" case)

- [ ] **Step 3: Add `show()` to `ClienteController` and wire the route**

Add this method to `app/Http/Controllers/Admin/ClienteController.php` (alongside `index()`/`store()`):

```php
    public function show(Cliente $cliente)
    {
        $cliente->load(['tickets' => fn ($q) => $q->latest()->limit(20)]);

        $faturacaoTotal = Pagamento::query()
            ->where('estado', 'pago')
            ->whereHas('orcamento.ticket', fn ($q) => $q->where('cliente_id', $cliente->id))
            ->sum('valor');

        $orcamentos = Orcamento::query()
            ->whereHas('ticket', fn ($q) => $q->where('cliente_id', $cliente->id))
            ->with('itens')
            ->latest('created_at')
            ->limit(20)
            ->get();

        return response()->json([
            'cliente' => [
                'id' => $cliente->id,
                'nome' => $cliente->nome,
                'email' => $cliente->email,
                'telefone' => $cliente->telefone,
                'morada' => $cliente->morada,
                'nif' => $cliente->nif,
                'notas' => $cliente->notas,
                'created_at' => $cliente->created_at,
            ],
            'resumo' => [
                'intervencoes_total' => $cliente->tickets->count(),
                'faturacao_total' => number_format((float) $faturacaoTotal, 2, '.', ''),
                'ultima_intervencao_em' => $cliente->tickets->max('created_at'),
            ],
            'intervencoes' => $cliente->tickets->map(fn (\App\Models\Ticket $t) => [
                'id' => $t->id,
                'titulo' => $t->titulo,
                'estado' => $t->estado->value,
                'categoria' => $t->categoria->value,
                'prioridade' => $t->prioridade->value,
                'created_at' => $t->created_at,
            ]),
            'orcamentos' => $orcamentos->map(fn (Orcamento $o) => [
                'id' => $o->id,
                'ticket_id' => $o->ticket_id,
                'valor_total' => number_format($o->total(), 2, '.', ''),
                'estado' => $o->estado->value,
                'created_at' => $o->created_at,
            ]),
        ]);
    }
```

Add the required imports to the top of the file:

```php
use App\Models\Orcamento;
use App\Models\Pagamento;
```

In `routes/api.php`, add right after `GET /clientes`:

```php
    Route::get('/clientes/{cliente}', [\App\Http\Controllers\Admin\ClienteController::class, 'show']);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ClienteShowEndpointTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/ClienteController.php routes/api.php tests/Feature/Admin/ClienteShowEndpointTest.php
git commit -m "feat: add GET /api/admin/clientes/{cliente} detail endpoint"
```

---

## Task 4: `GET /api/admin/dashboard`

**Files:**
- Create: `app/Http/Controllers/Admin/DashboardController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Admin/DashboardEndpointTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Orcamento;
use App\Models\Pagamento;
use App\Models\Ticket;
use App\Models\User;

function criarTicketParaDashboard(Cliente $cliente, TicketEstado $estado): Ticket
{
    return Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => $estado,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'Ticket dashboard',
        'descricao' => 'Descricao.',
    ]);
}

test('admin ve kpis do dashboard', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $cliente = Cliente::create(['nome' => 'Dashboard Cliente', 'email' => 'dash@example.com', 'telefone' => '911000004']);

    $abertoTicket = criarTicketParaDashboard($cliente, TicketEstado::Aberto);
    criarTicketParaDashboard($cliente, TicketEstado::Resolvido);
    criarTicketParaDashboard($cliente, TicketEstado::Cancelado);

    $orcamento = Orcamento::create(['ticket_id' => $abertoTicket->id, 'versao' => 1, 'estado' => 'aprovado', 'decided_at' => now()]);
    Pagamento::create(['orcamento_id' => $orcamento->id, 'estado' => 'pago', 'valor' => 100.00, 'paid_at' => now()]);

    $response = $this->actingAs($admin)->getJson('/api/admin/dashboard');

    $response->assertOk();
    $response->assertJsonPath('clientes.total', 1);
    $response->assertJsonPath('intervencoes.total', 3);
    $response->assertJsonPath('pendentes', 1);
    $response->assertJsonPath('faturacao_mes', '100.00');
    $response->assertJsonPath('agendamentos.total', 0);
    $response->assertJsonPath('por_estado.resolvido', 1);
    $response->assertJsonPath('por_estado.cancelado', 1);
    $response->assertJsonCount(3, 'intervencoes_recentes');
});

test('tecnico nao pode ver dashboard admin', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);

    $response = $this->actingAs($tecnico)->getJson('/api/admin/dashboard');

    $response->assertStatus(403);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DashboardEndpointTest`
Expected: FAIL — route not defined (404)

- [ ] **Step 3: Create `DashboardController`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TicketEstado;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Pagamento;
use App\Models\Ticket;

class DashboardController extends Controller
{
    public function index()
    {
        $inicioMes = now()->startOfMonth();
        $inicioSemana = now()->startOfWeek();

        $porEstado = collect(TicketEstado::cases())->mapWithKeys(
            fn (TicketEstado $estado) => [$estado->value => Ticket::where('estado', $estado)->count()]
        );

        $faturacaoMes = Pagamento::query()
            ->where('estado', 'pago')
            ->where('created_at', '>=', $inicioMes)
            ->sum('valor');

        $intervencoesRecentes = Ticket::with('cliente')
            ->latest()
            ->limit(5)
            ->get();

        return response()->json([
            'clientes' => [
                'total' => Cliente::count(),
                'novos_mes' => Cliente::where('created_at', '>=', $inicioMes)->count(),
            ],
            'intervencoes' => [
                'total' => Ticket::count(),
                'esta_semana' => Ticket::where('created_at', '>=', $inicioSemana)->count(),
            ],
            'faturacao_mes' => number_format((float) $faturacaoMes, 2, '.', ''),
            'pendentes' => Ticket::whereNotIn('estado', [TicketEstado::Resolvido, TicketEstado::Cancelado])->count(),
            'agendamentos' => ['total' => 0],
            'por_estado' => $porEstado,
            'intervencoes_recentes' => $intervencoesRecentes->map(fn (Ticket $t) => [
                'id' => $t->id,
                'titulo' => $t->titulo,
                'cliente_nome' => $t->cliente->nome,
                'estado' => $t->estado->value,
                'created_at' => $t->created_at,
            ]),
        ]);
    }
}
```

In `routes/api.php`, add right after `GET /clientes/{cliente}`:

```php
    Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index']);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=DashboardEndpointTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Run the full backend suite**

Run: `php artisan test`
Expected: PASS, 0 failures

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/DashboardController.php routes/api.php tests/Feature/Admin/DashboardEndpointTest.php
git commit -m "feat: add GET /api/admin/dashboard KPI endpoint"
```

---

## Task 5: Extract shared `TableSkeleton`/`EmptyState`

**Files:**
- Create: `src/components/table/TableParts.tsx`
- Modify: `src/routes/tickets-list.tsx`
- Modify: `src/routes/pagamentos-list.tsx`

- [ ] **Step 1: Create the shared component**

```tsx
export function TableSkeleton({ cols }: { cols: number }) {
  return (
    <div className="panel-tech overflow-hidden">
      {Array.from({ length: 5 }).map((_, row) => (
        <div key={row} className="flex gap-6 border-b border-border px-4 py-3.5 last:border-0">
          {Array.from({ length: cols }).map((_, col) => (
            <div key={col} className="h-4 flex-1 animate-pulse rounded bg-secondary" />
          ))}
        </div>
      ))}
    </div>
  );
}

const DEFAULT_EMPTY_ICON_PATH = 'M9 13h6m-6 4h6M9 5h6a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z';

export function EmptyState({ message, iconPath = DEFAULT_EMPTY_ICON_PATH }: { message: string; iconPath?: string }) {
  return (
    <div className="panel-tech flex flex-col items-center gap-2 px-6 py-16 text-center">
      <svg viewBox="0 0 24 24" className="size-10 text-muted-foreground" fill="none" stroke="currentColor" strokeWidth={1.5} aria-hidden="true">
        <path strokeLinecap="round" strokeLinejoin="round" d={iconPath} />
      </svg>
      <p className="text-sm text-muted-foreground">{message}</p>
    </div>
  );
}

export const CIRCLE_ALERT_ICON_PATH = 'M12 8v4m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z';
```

- [ ] **Step 2: Update `tickets-list.tsx`**

Remove the local `TableSkeleton`/`EmptyState` function definitions (lines 43-66 in the current file) and replace the import block at the top:

```tsx
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from '@tanstack/react-router';
import { apiFetch } from '@/lib/apiClient';
import { useAuth } from '@/lib/auth';
import { TableSkeleton, EmptyState } from '@/components/table/TableParts';
```

Everything else in the file (types, constants, `TicketsListPage`) stays the same — it already calls `<TableSkeleton cols={4} />` and `<EmptyState message="..." />`, which now resolve to the imported versions.

- [ ] **Step 3: Update `pagamentos-list.tsx`**

Remove the local `TableSkeleton`/`EmptyState` function definitions (lines 24-47 in the current file) and update the import block:

```tsx
import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { apiFetch } from '@/lib/apiClient';
import { TableSkeleton, EmptyState, CIRCLE_ALERT_ICON_PATH } from '@/components/table/TableParts';
```

Update the one call site that used the pagamentos-specific icon:

```tsx
{data && data.data.length === 0 && <EmptyState message="Nenhum pagamento encontrado." iconPath={CIRCLE_ALERT_ICON_PATH} />}
```

- [ ] **Step 4: Verify build**

Run: `npm run build`
Expected: builds successfully, no TypeScript errors, no unused-import warnings

- [ ] **Step 5: Commit**

```bash
git add src/components/table/TableParts.tsx src/routes/tickets-list.tsx src/routes/pagamentos-list.tsx
git commit -m "refactor: extract shared TableSkeleton/EmptyState components"
```

---

## Task 6: `PlaceholderPage` + full sidebar

**Files:**
- Create: `src/routes/placeholder.tsx`
- Modify: `src/routes/root.tsx`
- Modify: `src/router.tsx`

- [ ] **Step 1: Create `PlaceholderPage`**

```tsx
export function PlaceholderPage({ titulo }: { titulo: string }) {
  return (
    <div>
      <h1 className="mb-6 text-2xl font-bold text-foreground">{titulo}</h1>
      <div className="panel-tech flex flex-col items-center gap-2 px-6 py-16 text-center">
        <svg viewBox="0 0 24 24" className="size-10 text-muted-foreground" fill="none" stroke="currentColor" strokeWidth={1.5} aria-hidden="true">
          <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6l4 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
        <p className="text-sm text-muted-foreground">Módulo em breve.</p>
      </div>
    </div>
  );
}
```

- [ ] **Step 2: Wire placeholder routes in `router.tsx`**

Replace the full contents of `src/router.tsx`:

```tsx
import { createRootRoute, createRoute, createRouter } from '@tanstack/react-router';
import { RootLayout } from './routes/root';
import { LoginPage } from './routes/login';
import { DashboardPage } from './routes/dashboard';
import { ClientesListPage } from './routes/clientes-list';
import { ClienteDetailPage } from './routes/cliente-detail';
import { TicketsListPage } from './routes/tickets-list';
import { TicketDetailPage } from './routes/ticket-detail';
import { PagamentosListPage } from './routes/pagamentos-list';
import { PlaceholderPage } from './routes/placeholder';

const rootRoute = createRootRoute({ component: RootLayout });

const indexRoute = createRoute({ getParentRoute: () => rootRoute, path: '/', component: DashboardPage });
const loginRoute = createRoute({ getParentRoute: () => rootRoute, path: '/login', component: LoginPage });
const clientesRoute = createRoute({ getParentRoute: () => rootRoute, path: '/clientes', component: ClientesListPage });
const clienteDetailRoute = createRoute({ getParentRoute: () => rootRoute, path: '/clientes/$clienteId', component: ClienteDetailPage });
const ticketsRoute = createRoute({ getParentRoute: () => rootRoute, path: '/tickets', component: TicketsListPage });
const ticketDetailRoute = createRoute({ getParentRoute: () => rootRoute, path: '/tickets/$ticketId', component: TicketDetailPage });
const pagamentosRoute = createRoute({ getParentRoute: () => rootRoute, path: '/pagamentos', component: PagamentosListPage });
const agendamentosRoute = createRoute({ getParentRoute: () => rootRoute, path: '/agendamentos', component: () => <PlaceholderPage titulo="Agendamentos" /> });
const equipamentosRoute = createRoute({ getParentRoute: () => rootRoute, path: '/equipamentos', component: () => <PlaceholderPage titulo="Equipamentos" /> });
const faturasRoute = createRoute({ getParentRoute: () => rootRoute, path: '/faturas', component: () => <PlaceholderPage titulo="Faturas" /> });
const orcamentosRoute = createRoute({ getParentRoute: () => rootRoute, path: '/orcamentos', component: () => <PlaceholderPage titulo="Orçamentos" /> });
const comunicacoesRoute = createRoute({ getParentRoute: () => rootRoute, path: '/comunicacoes', component: () => <PlaceholderPage titulo="Comunicações" /> });
const documentosRoute = createRoute({ getParentRoute: () => rootRoute, path: '/documentos', component: () => <PlaceholderPage titulo="Documentos" /> });
const relatoriosRoute = createRoute({ getParentRoute: () => rootRoute, path: '/relatorios', component: () => <PlaceholderPage titulo="Relatórios" /> });
const definicoesRoute = createRoute({ getParentRoute: () => rootRoute, path: '/definicoes', component: () => <PlaceholderPage titulo="Definições" /> });

const routeTree = rootRoute.addChildren([
  indexRoute,
  loginRoute,
  clientesRoute,
  clienteDetailRoute,
  ticketsRoute,
  ticketDetailRoute,
  pagamentosRoute,
  agendamentosRoute,
  equipamentosRoute,
  faturasRoute,
  orcamentosRoute,
  comunicacoesRoute,
  documentosRoute,
  relatoriosRoute,
  definicoesRoute,
]);

export const router = createRouter({ routeTree });

declare module '@tanstack/react-router' {
  interface Register {
    router: typeof router;
  }
}
```

- [ ] **Step 3: Update sidebar nav in `root.tsx`**

Replace the `<nav>` block inside `RootLayout` (currently just Tickets/Pagamentos/Conteúdo):

```tsx
          <nav className="flex flex-col gap-1">
            <Link to="/" className={NAV_LINK_CLASS} activeProps={{ className: `${NAV_LINK_CLASS} bg-secondary text-foreground` }}>
              Dashboard
            </Link>
            <Link to="/clientes" className={NAV_LINK_CLASS} activeProps={{ className: `${NAV_LINK_CLASS} bg-secondary text-foreground` }}>
              Clientes
            </Link>
            <Link to="/tickets" className={NAV_LINK_CLASS} activeProps={{ className: `${NAV_LINK_CLASS} bg-secondary text-foreground` }}>
              Intervenções
            </Link>
            <Link to="/agendamentos" className={NAV_LINK_CLASS} activeProps={{ className: `${NAV_LINK_CLASS} bg-secondary text-foreground` }}>
              Agendamentos
            </Link>
            <Link to="/equipamentos" className={NAV_LINK_CLASS} activeProps={{ className: `${NAV_LINK_CLASS} bg-secondary text-foreground` }}>
              Equipamentos
            </Link>
            {user.role === 'admin' && (
              <Link to="/faturas" className={NAV_LINK_CLASS} activeProps={{ className: `${NAV_LINK_CLASS} bg-secondary text-foreground` }}>
                Faturas
              </Link>
            )}
            {user.role === 'admin' && (
              <Link to="/pagamentos" className={NAV_LINK_CLASS} activeProps={{ className: `${NAV_LINK_CLASS} bg-secondary text-foreground` }}>
                Pagamentos
              </Link>
            )}
            <Link to="/orcamentos" className={NAV_LINK_CLASS} activeProps={{ className: `${NAV_LINK_CLASS} bg-secondary text-foreground` }}>
              Orçamentos
            </Link>
            <Link to="/comunicacoes" className={NAV_LINK_CLASS} activeProps={{ className: `${NAV_LINK_CLASS} bg-secondary text-foreground` }}>
              Comunicações
            </Link>
            <Link to="/documentos" className={NAV_LINK_CLASS} activeProps={{ className: `${NAV_LINK_CLASS} bg-secondary text-foreground` }}>
              Documentos
            </Link>
            {user.role === 'admin' && (
              <Link to="/relatorios" className={NAV_LINK_CLASS} activeProps={{ className: `${NAV_LINK_CLASS} bg-secondary text-foreground` }}>
                Relatórios
              </Link>
            )}
            <Link to="/definicoes" className={NAV_LINK_CLASS} activeProps={{ className: `${NAV_LINK_CLASS} bg-secondary text-foreground` }}>
              Definições
            </Link>
            {user.role === 'admin' && (
              <a href={CONTEUDO_SITE_URL} target="_blank" rel="noreferrer" className={NAV_LINK_CLASS}>
                Conteúdo site ↗
              </a>
            )}
          </nav>
```

(This step depends on Task 7/8/9 creating `dashboard.tsx`, `clientes-list.tsx`, `cliente-detail.tsx` — do this step last, after those files exist, or the `router.tsx` import will fail to compile. Recommended order: do Tasks 7, 8, 9 first, then come back and do Task 6's `router.tsx`/`root.tsx` edits as the final wiring step.)

- [ ] **Step 4: Verify build**

Run: `npm run build`
Expected: builds successfully

- [ ] **Step 5: Commit**

```bash
git add src/routes/placeholder.tsx src/router.tsx src/routes/root.tsx
git commit -m "feat: add full mockup sidebar navigation with placeholder modules"
```

---

## Task 7: `DashboardPage`

**Files:**
- Create: `src/routes/dashboard.tsx`

- [ ] **Step 1: Write the page**

```tsx
import { useQuery } from '@tanstack/react-query';
import { Link } from '@tanstack/react-router';
import { apiFetch } from '@/lib/apiClient';

type DashboardData = {
  clientes: { total: number; novos_mes: number };
  intervencoes: { total: number; esta_semana: number };
  faturacao_mes: string;
  pendentes: number;
  agendamentos: { total: number };
  por_estado: Record<string, number>;
  intervencoes_recentes: { id: number; titulo: string; cliente_nome: string; estado: string; created_at: string }[];
};

const ESTADO_COLORS: Record<string, string> = {
  aberto: '#f59e0b',
  em_analise: '#f59e0b',
  em_curso: '#38bdf8',
  aguarda_cliente: '#64748b',
  aguarda_peca: '#64748b',
  em_testes: '#38bdf8',
  resolvido: '#22c55e',
  cancelado: '#ef4444',
};

function KpiCard({ label, value, hint }: { label: string; value: string | number; hint?: string }) {
  return (
    <div className="panel-tech p-5">
      <p className="label-tech text-muted-foreground">{label}</p>
      <p className="mt-2 text-3xl font-bold text-foreground">{value}</p>
      {hint && <p className="mt-1 text-xs text-muted-foreground">{hint}</p>}
    </div>
  );
}

function Donut({ porEstado }: { porEstado: Record<string, number> }) {
  const entries = Object.entries(porEstado).filter(([, count]) => count > 0);
  const total = entries.reduce((sum, [, count]) => sum + count, 0);
  if (total === 0) {
    return <p className="text-sm text-muted-foreground">Sem dados.</p>;
  }

  const radius = 40;
  const circumference = 2 * Math.PI * radius;
  let offsetAcc = 0;

  return (
    <div className="flex items-center gap-6">
      <svg viewBox="0 0 100 100" className="size-32 -rotate-90">
        {entries.map(([estado, count]) => {
          const fraction = count / total;
          const dash = fraction * circumference;
          const circle = (
            <circle
              key={estado}
              cx="50"
              cy="50"
              r={radius}
              fill="none"
              stroke={ESTADO_COLORS[estado] ?? '#64748b'}
              strokeWidth="14"
              strokeDasharray={`${dash} ${circumference - dash}`}
              strokeDashoffset={-offsetAcc}
            />
          );
          offsetAcc += dash;
          return circle;
        })}
      </svg>
      <ul className="space-y-1 text-sm">
        {entries.map(([estado, count]) => (
          <li key={estado} className="flex items-center gap-2 text-foreground/80">
            <span className="size-2.5 rounded-full" style={{ backgroundColor: ESTADO_COLORS[estado] ?? '#64748b' }} />
            {estado} ({count})
          </li>
        ))}
      </ul>
    </div>
  );
}

export function DashboardPage() {
  const { data, isLoading, error } = useQuery({
    queryKey: ['dashboard'],
    queryFn: () => apiFetch<DashboardData>('/api/admin/dashboard'),
  });

  if (isLoading) return <p className="label-tech text-muted-foreground">A carregar...</p>;
  if (error || !data) return <p role="alert" className="text-sm text-destructive">Erro ao carregar dashboard.</p>;

  return (
    <div>
      <h1 className="mb-6 text-2xl font-bold text-foreground">Dashboard</h1>

      <div className="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-5">
        <KpiCard label="Clientes" value={data.clientes.total} hint={`+${data.clientes.novos_mes} este mês`} />
        <KpiCard label="Intervenções" value={data.intervencoes.total} hint={`+${data.intervencoes.esta_semana} esta semana`} />
        <KpiCard label="Faturação (mês)" value={`${data.faturacao_mes}€`} />
        <KpiCard label="Pendentes" value={data.pendentes} />
        <div className="panel-tech p-5 opacity-60">
          <div className="flex items-center justify-between">
            <p className="label-tech text-muted-foreground">Agendamentos</p>
            <span className="rounded-full bg-muted px-2 py-0.5 text-[10px] uppercase tracking-wide text-muted-foreground">Em breve</span>
          </div>
          <p className="mt-2 text-3xl font-bold text-foreground">{data.agendamentos.total}</p>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <div className="panel-tech p-5">
          <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Por estado</h2>
          <Donut porEstado={data.por_estado} />
        </div>

        <div className="panel-tech p-5">
          <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Intervenções recentes</h2>
          {data.intervencoes_recentes.length === 0 && <p className="text-sm text-muted-foreground">Sem intervenções recentes.</p>}
          <ul className="space-y-3">
            {data.intervencoes_recentes.map((item) => (
              <li key={item.id} className="flex items-center justify-between border-b border-border pb-2 last:border-0">
                <div>
                  <Link to="/tickets/$ticketId" params={{ ticketId: String(item.id) }} className="font-medium text-electric-soft hover:underline">
                    {item.titulo}
                  </Link>
                  <p className="text-xs text-muted-foreground">{item.cliente_nome}</p>
                </div>
                <span className="text-xs text-muted-foreground">{item.estado}</span>
              </li>
            ))}
          </ul>
        </div>
      </div>
    </div>
  );
}
```

- [ ] **Step 2: Manual verification**

Run: `npm run dev`, log in as admin, load `/`. Confirm KPI cards render with real numbers, donut renders without crashing when a status has 0 tickets, recent list links to `/tickets/$ticketId`.

- [ ] **Step 3: Commit**

```bash
git add src/routes/dashboard.tsx
git commit -m "feat: add DashboardPage with KPI cards and status donut"
```

---

## Task 8: `ClientesListPage`

**Files:**
- Create: `src/routes/clientes-list.tsx`

- [ ] **Step 1: Write the page**

```tsx
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from '@tanstack/react-router';
import { apiFetch } from '@/lib/apiClient';
import { TableSkeleton, EmptyState } from '@/components/table/TableParts';

type ClienteRow = {
  id: number;
  nome: string;
  email: string | null;
  telefone: string | null;
  intervencoes_count: number;
};

type ClientesPage = { data: ClienteRow[]; meta: { current_page: number; last_page: number; total: number } };

export function ClientesListPage() {
  const [search, setSearch] = useState('');

  const { data, isLoading, error } = useQuery({
    queryKey: ['clientes', search],
    queryFn: () => apiFetch<ClientesPage>(`/api/admin/clientes${search ? `?search=${encodeURIComponent(search)}` : ''}`),
  });

  return (
    <div>
      <h1 className="mb-6 text-2xl font-bold text-foreground">Clientes</h1>
      <input
        type="search"
        value={search}
        onChange={(e) => setSearch(e.target.value)}
        placeholder="Pesquisar por nome, email ou telefone..."
        className="mb-6 w-full max-w-sm rounded-md border border-input bg-secondary px-3 py-2 text-sm text-foreground outline-none focus-visible:ring-2 focus-visible:ring-electric-soft"
      />

      {isLoading && <TableSkeleton cols={4} />}
      {error && <p role="alert" className="text-sm text-destructive">Erro ao carregar clientes.</p>}
      {data && data.data.length === 0 && <EmptyState message="Nenhum cliente encontrado." />}
      {data && data.data.length > 0 && (
        <div className="panel-tech overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border text-xs uppercase tracking-wide text-muted-foreground">
                <th className="px-4 py-3 font-medium">Nome</th>
                <th className="px-4 py-3 font-medium">Email</th>
                <th className="px-4 py-3 font-medium">Telefone</th>
                <th className="px-4 py-3 font-medium">Intervenções</th>
              </tr>
            </thead>
            <tbody>
              {data.data.map((cliente) => (
                <tr key={cliente.id} className="border-b border-border last:border-0 hover:bg-secondary/50">
                  <td className="px-4 py-3">
                    <Link to="/clientes/$clienteId" params={{ clienteId: String(cliente.id) }} className="font-medium text-electric-soft hover:underline">
                      {cliente.nome}
                    </Link>
                  </td>
                  <td className="px-4 py-3 text-foreground/80">{cliente.email ?? '—'}</td>
                  <td className="px-4 py-3 text-foreground/80">{cliente.telefone ?? '—'}</td>
                  <td className="px-4 py-3 text-foreground/80">{cliente.intervencoes_count}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
```

- [ ] **Step 2: Manual verification**

Run: `npm run dev`, load `/clientes`, confirm search debounced-free typing still filters correctly (no debounce needed at this data scale — React Query refetches on `search` key change), and empty state / skeleton render correctly.

- [ ] **Step 3: Commit**

```bash
git add src/routes/clientes-list.tsx
git commit -m "feat: add ClientesListPage with search"
```

---

## Task 9: `ClienteDetailPage`

**Files:**
- Create: `src/routes/cliente-detail.tsx`

- [ ] **Step 1: Write the page**

```tsx
import { useState } from 'react';
import { useParams } from '@tanstack/react-router';
import { useQuery } from '@tanstack/react-query';
import { apiFetch } from '@/lib/apiClient';
import { EmptyState } from '@/components/table/TableParts';

type ClienteDetail = {
  cliente: { id: number; nome: string; email: string | null; telefone: string | null; morada: string | null; nif: string | null; notas: string | null };
  resumo: { intervencoes_total: number; faturacao_total: string; ultima_intervencao_em: string | null };
  intervencoes: { id: number; titulo: string; estado: string; categoria: string; prioridade: string; created_at: string }[];
  orcamentos: { id: number; ticket_id: number; valor_total: string; estado: string; created_at: string }[];
};

const TABS = ['Resumo', 'Intervenções', 'Equipamentos', 'Faturas', 'Orçamentos', 'Documentos', 'Comunicações'] as const;
type Tab = (typeof TABS)[number];

export function ClienteDetailPage() {
  const { clienteId } = useParams({ from: '/clientes/$clienteId' });
  const [tab, setTab] = useState<Tab>('Resumo');

  const { data, isLoading, error } = useQuery({
    queryKey: ['cliente', clienteId],
    queryFn: () => apiFetch<ClienteDetail>(`/api/admin/clientes/${clienteId}`),
  });

  if (isLoading) return <p className="label-tech text-muted-foreground">A carregar...</p>;
  if (error || !data) return <p role="alert" className="text-sm text-destructive">Erro ao carregar cliente.</p>;

  return (
    <div>
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-foreground">{data.cliente.nome}</h1>
        <p className="text-sm text-muted-foreground">
          {[data.cliente.email, data.cliente.telefone, data.cliente.morada, data.cliente.nif].filter(Boolean).join(' · ') || 'Sem dados de contacto.'}
        </p>
      </div>

      <div className="mb-6 flex flex-wrap gap-1 border-b border-border">
        {TABS.map((t) => (
          <button
            key={t}
            onClick={() => setTab(t)}
            className={`cursor-pointer border-b-2 px-3 py-2 text-sm font-medium transition-colors ${
              tab === t ? 'border-electric-soft text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'
            }`}
          >
            {t}
          </button>
        ))}
      </div>

      {tab === 'Resumo' && (
        <div className="grid grid-cols-3 gap-4">
          <div className="panel-tech p-5">
            <p className="label-tech text-muted-foreground">Intervenções</p>
            <p className="mt-2 text-2xl font-bold text-foreground">{data.resumo.intervencoes_total}</p>
          </div>
          <div className="panel-tech p-5">
            <p className="label-tech text-muted-foreground">Faturação total</p>
            <p className="mt-2 text-2xl font-bold text-foreground">{data.resumo.faturacao_total}€</p>
          </div>
          <div className="panel-tech p-5">
            <p className="label-tech text-muted-foreground">Última intervenção</p>
            <p className="mt-2 text-2xl font-bold text-foreground">
              {data.resumo.ultima_intervencao_em ? new Date(data.resumo.ultima_intervencao_em).toLocaleDateString('pt-PT') : '—'}
            </p>
          </div>
        </div>
      )}

      {tab === 'Intervenções' && (
        data.intervencoes.length === 0 ? (
          <EmptyState message="Sem intervenções." />
        ) : (
          <div className="panel-tech overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-border text-xs uppercase tracking-wide text-muted-foreground">
                  <th className="px-4 py-3 font-medium">Título</th>
                  <th className="px-4 py-3 font-medium">Estado</th>
                  <th className="px-4 py-3 font-medium">Categoria</th>
                  <th className="px-4 py-3 font-medium">Prioridade</th>
                </tr>
              </thead>
              <tbody>
                {data.intervencoes.map((i) => (
                  <tr key={i.id} className="border-b border-border last:border-0">
                    <td className="px-4 py-3 text-foreground/80">{i.titulo}</td>
                    <td className="px-4 py-3 text-foreground/80">{i.estado}</td>
                    <td className="px-4 py-3 text-foreground/80">{i.categoria}</td>
                    <td className="px-4 py-3 text-foreground/80">{i.prioridade}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )
      )}

      {tab === 'Orçamentos' && (
        data.orcamentos.length === 0 ? (
          <EmptyState message="Sem orçamentos." />
        ) : (
          <div className="panel-tech overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-border text-xs uppercase tracking-wide text-muted-foreground">
                  <th className="px-4 py-3 font-medium">Ticket</th>
                  <th className="px-4 py-3 font-medium">Valor</th>
                  <th className="px-4 py-3 font-medium">Estado</th>
                </tr>
              </thead>
              <tbody>
                {data.orcamentos.map((o) => (
                  <tr key={o.id} className="border-b border-border last:border-0">
                    <td className="px-4 py-3 text-foreground/80">#{o.ticket_id}</td>
                    <td className="px-4 py-3 text-foreground/80">{o.valor_total}€</td>
                    <td className="px-4 py-3 text-foreground/80">{o.estado}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )
      )}

      {tab === 'Equipamentos' && <EmptyState message="Módulo em breve." />}
      {tab === 'Faturas' && <EmptyState message="Módulo em breve." />}
      {tab === 'Documentos' && <EmptyState message="Módulo em breve." />}
      {tab === 'Comunicações' && <EmptyState message="Módulo em breve." />}
    </div>
  );
}
```

- [ ] **Step 2: Manual verification**

Run: `npm run dev`, load `/clientes/1` (or whichever id exists), click through all 7 tabs, confirm the 3 data tabs render real data and the 4 empty tabs show "Módulo em breve."

- [ ] **Step 3: Commit**

```bash
git add src/routes/cliente-detail.tsx
git commit -m "feat: add ClienteDetailPage with 7 mockup tabs"
```

---

## Task 10: Final integration — wire router/sidebar (Task 6 steps 2-5) and full verification

**Files:** (already listed in Task 6 — this task is the execution checkpoint once Tasks 7-9 exist)

- [ ] **Step 1: Complete Task 6 Steps 2-5** now that `dashboard.tsx`, `clientes-list.tsx`, `cliente-detail.tsx` exist.

- [ ] **Step 2: Full backend test suite**

Run: `php artisan test`
Expected: PASS, 0 failures

- [ ] **Step 3: Full frontend build**

Run: `npm run build`
Expected: builds successfully, 0 TypeScript errors

- [ ] **Step 4: Manual smoke test**

Run: `npm run dev` (frontend) against the existing dev/staging API. Log in as admin. Walk through:
1. `/` → Dashboard loads, KPIs non-crashing
2. `/clientes` → list loads, search filters
3. `/clientes/$id` → detail loads, all 7 tabs clickable
4. `/tickets`, `/pagamentos` → still work (regression check)
5. Any placeholder route (`/agendamentos`, etc.) → shows "Módulo em breve" without crashing
6. Sidebar shows all 11 mockup items

- [ ] **Step 5: Deploy**

Follow the existing cPanel deploy flow (per `project_rui-tech-helper_deploy` memory): commit, push both repos to GitHub master, `mcp__cpanel__update_git_repo` (with explicit `branch: master`) then `mcp__cpanel__deploy_git_repo` for both `rui-tech-helper-api-src` and `rui-tech-helper-crm-src3`, then verify via direct `curl` against production URLs (not just deploy-status) that both new backend endpoints and the new frontend bundle are actually live.
