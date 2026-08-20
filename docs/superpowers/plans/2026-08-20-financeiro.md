# Financeiro (Pagamentos IfthenPay + Facturação Moloni) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ligar orçamento aceite (já existente) a pagamento real via IfthenPay (MB/MBWay) e emissão automática de Factura-Recibo via Moloni, só depois do pagamento confirmado.

**Architecture:** `Pagamento` (1:1 `Orcamento`) guarda estado do pagamento e do documento fiscal. `IfthenPayService` gera referências/pedidos. Confirmação (webhook IfthenPay ou marcação manual admin) despacha job em fila `EmitirFacturaRecibo`, que usa `MoloniService` (OAuth2 + criação de documento) e envia email via Resend. Nenhum documento fiscal existe antes de `Pagamento->estado = pago`.

**Tech Stack:** Laravel 13, Pest, `Http::fake()` para IfthenPay/Moloni, fila `database` já configurada.

**Spec:** `docs/superpowers/specs/2026-08-20-financeiro-design.md`

---

## Task 1: Configuração fiscal e credenciais de serviços

**Files:**
- Create: `config/fiscal.php`
- Modify: `config/services.php`

- [ ] **Step 1: Criar `config/fiscal.php`**

```php
<?php

return [
    'isento_iva' => (bool) env('FISCAL_ISENTO_IVA', true),
    'iva_taxa' => (int) env('FISCAL_IVA_TAXA', 23),
    'motivo_isencao' => env('FISCAL_MOTIVO_ISENCAO', 'Isento de IVA'),
];
```

- [ ] **Step 2: Adicionar entradas `ifthenpay`/`moloni` a `config/services.php`**

Adicionar dentro do array retornado, antes do `];` final:

```php
    'ifthenpay' => [
        'mb_key' => env('IFTHENPAY_MB_KEY'),
        'mbway_key' => env('IFTHENPAY_MBWAY_KEY'),
        'antiphishing_key' => env('IFTHENPAY_ANTIPHISHING_KEY'),
    ],

    'moloni' => [
        'client_id' => env('MOLONI_CLIENT_ID'),
        'client_secret' => env('MOLONI_CLIENT_SECRET'),
        'company_id' => env('MOLONI_COMPANY_ID'),
        'iva_tax_id' => env('MOLONI_IVA_TAX_ID'),
    ],
```

- [ ] **Step 3: Verificar**

Run: `php artisan config:clear && php artisan tinker --execute="echo config('fiscal.isento_iva') ? 'true' : 'false';"`
Expected: `true`

- [ ] **Step 4: Commit**

```bash
git add config/fiscal.php config/services.php
git commit -m "feat(financeiro): config fiscal e credenciais ifthenpay/moloni"
```

---

## Task 2: Enums do domínio financeiro

**Files:**
- Create: `app/Enums/PagamentoMetodo.php`
- Create: `app/Enums/PagamentoEstado.php`
- Create: `app/Enums/PagamentoOrigem.php`

- [ ] **Step 1: `app/Enums/PagamentoMetodo.php`**

```php
<?php

namespace App\Enums;

enum PagamentoMetodo: string
{
    case Mb = 'mb';
    case Mbway = 'mbway';
}
```

- [ ] **Step 2: `app/Enums/PagamentoEstado.php`**

```php
<?php

namespace App\Enums;

enum PagamentoEstado: string
{
    case Pendente = 'pendente';
    case Pago = 'pago';
    case Expirado = 'expirado';
    case Cancelado = 'cancelado';
}
```

- [ ] **Step 3: `app/Enums/PagamentoOrigem.php`**

```php
<?php

namespace App\Enums;

enum PagamentoOrigem: string
{
    case Ifthenpay = 'ifthenpay';
    case Manual = 'manual';
}
```

- [ ] **Step 4: Commit**

```bash
git add app/Enums/PagamentoMetodo.php app/Enums/PagamentoEstado.php app/Enums/PagamentoOrigem.php
git commit -m "feat(financeiro): enums PagamentoMetodo/Estado/Origem"
```

---

## Task 3: Migrations + models `Pagamento` e `MoloniCredential`

**Files:**
- Create: `database/migrations/2026_08_20_140000_create_pagamentos_table.php`
- Create: `database/migrations/2026_08_20_140100_create_moloni_credentials_table.php`
- Create: `app/Models/Pagamento.php`
- Create: `app/Models/MoloniCredential.php`
- Test: `tests/Feature/PagamentoModelTest.php`

- [ ] **Step 1: Migration `pagamentos`**

```php
<?php
// database/migrations/2026_08_20_140000_create_pagamentos_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orcamento_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('metodo')->nullable();
            $table->string('estado')->default('pendente');
            $table->string('ifthenpay_request_id')->nullable();
            $table->string('entidade')->nullable();
            $table->string('referencia')->nullable();
            $table->string('telefone')->nullable();
            $table->decimal('valor', 10, 2);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('origem')->nullable();
            $table->string('moloni_document_id')->nullable();
            $table->string('moloni_numero_documento')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagamentos');
    }
};
```

- [ ] **Step 2: Migration `moloni_credentials`**

```php
<?php
// database/migrations/2026_08_20_140100_create_moloni_credentials_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moloni_credentials', function (Blueprint $table) {
            $table->id();
            $table->text('access_token');
            $table->text('refresh_token');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moloni_credentials');
    }
};
```

- [ ] **Step 3: Run migrations**

Run: `php artisan migrate`
Expected: both tables created without error.

- [ ] **Step 4: Write failing test for `Pagamento` model behaviour**

```php
<?php
// tests/Feature/PagamentoModelTest.php
use App\Enums\PagamentoEstado;
use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Enums\UserRole;
use App\Models\Cliente;
use App\Models\Orcamento;
use App\Models\Pagamento;
use App\Models\Ticket;
use App\Models\User;

function criarOrcamentoParaPagamento(): Orcamento
{
    $clienteUser = User::factory()->create(['role' => UserRole::Cliente]);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Teste',
        'email' => $clienteUser->email,
        'telefone' => '912345678',
        'nif' => '123456789',
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

    return Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => 'aprovado', 'decided_at' => now()]);
}

test('pagamento pendente com expires_at no passado fica expirado', function () {
    $orcamento = criarOrcamentoParaPagamento();
    $pagamento = Pagamento::create([
        'orcamento_id' => $orcamento->id,
        'estado' => PagamentoEstado::Pendente,
        'valor' => 50,
        'expires_at' => now()->subHour(),
    ]);

    expect($pagamento->estaExpirado())->toBeTrue();
    expect($pagamento->estado_efetivo)->toBe('expirado');
});

test('pagamento pendente sem expires_at nao esta expirado', function () {
    $orcamento = criarOrcamentoParaPagamento();
    $pagamento = Pagamento::create([
        'orcamento_id' => $orcamento->id,
        'estado' => PagamentoEstado::Pendente,
        'valor' => 50,
    ]);

    expect($pagamento->estaExpirado())->toBeFalse();
    expect($pagamento->estado_efetivo)->toBe('pendente');
});

test('pagamento pago nunca esta expirado mesmo com expires_at passado', function () {
    $orcamento = criarOrcamentoParaPagamento();
    $pagamento = Pagamento::create([
        'orcamento_id' => $orcamento->id,
        'estado' => PagamentoEstado::Pago,
        'valor' => 50,
        'expires_at' => now()->subHour(),
        'paid_at' => now(),
    ]);

    expect($pagamento->estaExpirado())->toBeFalse();
    expect($pagamento->estado_efetivo)->toBe('pago');
});
```

- [ ] **Step 5: Run test to verify it fails**

Run: `php artisan test tests/Feature/PagamentoModelTest.php`
Expected: FAIL — `Class "App\Models\Pagamento" not found`

- [ ] **Step 6: `app/Models/Pagamento.php`**

```php
<?php

namespace App\Models;

use App\Enums\PagamentoEstado;
use App\Enums\PagamentoMetodo;
use App\Enums\PagamentoOrigem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pagamento extends Model
{
    protected $fillable = [
        'orcamento_id',
        'metodo',
        'estado',
        'ifthenpay_request_id',
        'entidade',
        'referencia',
        'telefone',
        'valor',
        'expires_at',
        'paid_at',
        'origem',
        'moloni_document_id',
        'moloni_numero_documento',
    ];

    protected $appends = ['estado_efetivo'];

    protected function casts(): array
    {
        return [
            'metodo' => PagamentoMetodo::class,
            'estado' => PagamentoEstado::class,
            'origem' => PagamentoOrigem::class,
            'valor' => 'decimal:2',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function orcamento(): BelongsTo
    {
        return $this->belongsTo(Orcamento::class);
    }

    public function estaExpirado(): bool
    {
        return $this->estado === PagamentoEstado::Pendente
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    public function getEstadoEfetivoAttribute(): string
    {
        return $this->estaExpirado() ? PagamentoEstado::Expirado->value : $this->estado->value;
    }
}
```

- [ ] **Step 7: `app/Models/MoloniCredential.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MoloniCredential extends Model
{
    protected $fillable = ['access_token', 'refresh_token', 'expires_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}
```

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test tests/Feature/PagamentoModelTest.php`
Expected: PASS (3 tests)

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_08_20_140000_create_pagamentos_table.php database/migrations/2026_08_20_140100_create_moloni_credentials_table.php app/Models/Pagamento.php app/Models/MoloniCredential.php tests/Feature/PagamentoModelTest.php
git commit -m "feat(financeiro): tabelas e models Pagamento/MoloniCredential"
```

---

## Task 4: Guarda de NIF + criação de `Pagamento` ao aceitar orçamento

**Files:**
- Modify: `app/Models/Orcamento.php`
- Modify: `app/Http/Controllers/Tickets/OrcamentoController.php:47-63`
- Test: `tests/Feature/OrcamentoEndpointTest.php`

- [ ] **Step 1: Escrever testes que falham em `tests/Feature/OrcamentoEndpointTest.php`**

Adicionar ao fim do ficheiro (usa a função `criarTicketComTecnicoParaOrcamento` já existente no ficheiro):

```php
test('cliente sem nif nao consegue aprovar orcamento', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketComTecnicoParaOrcamento($tecnico);
    $ticket->cliente->update(['nif' => null]);
    $orcamento = Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => 'pendente']);

    $response = $this->actingAs($ticket->cliente->user)->postJson("/api/cliente/orcamentos/{$orcamento->id}/decisao", [
        'decisao' => 'aprovado',
    ]);

    $response->assertStatus(422);
    expect(\App\Models\Pagamento::count())->toBe(0);
});

test('cliente com nif aprova orcamento e cria pagamento pendente com valor do orcamento', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketComTecnicoParaOrcamento($tecnico);
    $ticket->cliente->update(['nif' => '123456789']);
    $orcamento = Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => 'pendente']);
    $orcamento->itens()->create(['descricao' => 'Fonte', 'quantidade' => 1, 'preco_unitario' => 45.50]);

    $response = $this->actingAs($ticket->cliente->user)->postJson("/api/cliente/orcamentos/{$orcamento->id}/decisao", [
        'decisao' => 'aprovado',
    ]);

    $response->assertStatus(200);
    $pagamento = \App\Models\Pagamento::where('orcamento_id', $orcamento->id)->first();
    expect($pagamento)->not->toBeNull();
    expect($pagamento->estado->value)->toBe('pendente');
    expect((float) $pagamento->valor)->toBe(45.50);
});

test('cliente rejeita orcamento e nao cria pagamento', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketComTecnicoParaOrcamento($tecnico);
    $orcamento = Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => 'pendente']);

    $this->actingAs($ticket->cliente->user)->postJson("/api/cliente/orcamentos/{$orcamento->id}/decisao", [
        'decisao' => 'rejeitado',
    ])->assertStatus(200);

    expect(\App\Models\Pagamento::count())->toBe(0);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/OrcamentoEndpointTest.php`
Expected: FAIL — sem NIF continua a aprovar (422 esperado, recebe 200); pagamento nunca é criado.

- [ ] **Step 3: Adicionar relação `pagamento()` em `app/Models/Orcamento.php`**

Adicionar import `use Illuminate\Database\Eloquent\Relations\HasOne;` e o método, depois de `itens()`:

```php
    public function pagamento(): HasOne
    {
        return $this->hasOne(Pagamento::class);
    }
```

- [ ] **Step 4: Modificar `decisao()` em `app/Http/Controllers/Tickets/OrcamentoController.php`**

Adicionar imports no topo:

```php
use App\Enums\PagamentoEstado;
use App\Models\Pagamento;
```

Substituir o método `decisao()` inteiro por:

```php
    public function decisao(Request $request, Orcamento $orcamento)
    {
        $cliente = $request->user()->cliente;
        abort_if($cliente === null || $orcamento->ticket->cliente_id !== $cliente->id, 403);
        abort_if($orcamento->estado->value !== 'pendente', 409, 'Orcamento ja foi decidido.');

        $data = $request->validate([
            'decisao' => ['required', 'in:aprovado,rejeitado'],
        ]);

        if ($data['decisao'] === 'aprovado') {
            abort_if(empty($cliente->nif), 422, 'Complete o NIF no seu perfil antes de aceitar o orcamento.');
        }

        $orcamento->update([
            'estado' => $data['decisao'],
            'decided_at' => now(),
        ]);

        if ($data['decisao'] === 'aprovado') {
            Pagamento::create([
                'orcamento_id' => $orcamento->id,
                'estado' => PagamentoEstado::Pendente,
                'valor' => $orcamento->fresh('itens')->total(),
            ]);
        }

        return response()->json(['orcamento' => $orcamento->fresh()]);
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/OrcamentoEndpointTest.php`
Expected: PASS (all tests, including the 3 new ones)

- [ ] **Step 6: Run full suite to check for regressions**

Run: `php artisan test`
Expected: all passing

- [ ] **Step 7: Commit**

```bash
git add app/Models/Orcamento.php app/Http/Controllers/Tickets/OrcamentoController.php tests/Feature/OrcamentoEndpointTest.php
git commit -m "feat(financeiro): bloquear aprovacao sem NIF e criar Pagamento pendente"
```

---

## Task 5: `IfthenPayService` — gerar referência MB e pedido MBWay

**Files:**
- Create: `app/Services/IfthenPayService.php`
- Test: `tests/Feature/IfthenPayServiceTest.php`

- [ ] **Step 1: Escrever teste que falha**

```php
<?php
// tests/Feature/IfthenPayServiceTest.php
use App\Enums\PagamentoEstado;
use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Enums\UserRole;
use App\Models\Cliente;
use App\Models\Orcamento;
use App\Models\Pagamento;
use App\Models\Ticket;
use App\Models\User;
use App\Services\IfthenPayService;
use Illuminate\Support\Facades\Http;

function criarPagamentoPendente(): Pagamento
{
    $clienteUser = User::factory()->create(['role' => UserRole::Cliente]);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Teste',
        'email' => $clienteUser->email,
        'telefone' => '912345678',
        'nif' => '123456789',
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
    $orcamento = Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => 'aprovado', 'decided_at' => now()]);

    return Pagamento::create(['orcamento_id' => $orcamento->id, 'estado' => PagamentoEstado::Pendente, 'valor' => 45.50]);
}

test('gerarReferenciaMb grava entidade referencia e expiracao', function () {
    Http::fake([
        'ifthenpay.com/api/multibanco/reference/init' => Http::response([
            'Entidade' => '12345',
            'Referencia' => '123456789',
            'RequestId' => 'req-1',
            'Amount' => '45.50',
        ], 200),
    ]);

    $pagamento = criarPagamentoPendente();
    $resultado = (new IfthenPayService)->gerarReferenciaMb($pagamento);

    expect($resultado->metodo->value)->toBe('mb');
    expect($resultado->entidade)->toBe('12345');
    expect($resultado->referencia)->toBe('123456789');
    expect($resultado->ifthenpay_request_id)->toBe('req-1');
    expect($resultado->expires_at->diffInHours(now()))->toBeLessThanOrEqual(48);
});

test('gerarReferenciaMb lanca excecao quando ifthenpay falha', function () {
    Http::fake([
        'ifthenpay.com/api/multibanco/reference/init' => Http::response(['Message' => 'chave invalida'], 400),
    ]);

    $pagamento = criarPagamentoPendente();

    expect(fn () => (new IfthenPayService)->gerarReferenciaMb($pagamento))->toThrow(RuntimeException::class);
});

test('gerarPedidoMbway grava telefone e pedido', function () {
    Http::fake([
        'ifthenpay.com/api/mbway/mb/wayrequest' => Http::response([
            'RequestId' => 'req-2',
            'Status' => '000',
            'Message' => 'Pedido enviado',
        ], 200),
    ]);

    $pagamento = criarPagamentoPendente();
    $resultado = (new IfthenPayService)->gerarPedidoMbway($pagamento, '912345678');

    expect($resultado->metodo->value)->toBe('mbway');
    expect($resultado->telefone)->toBe('912345678');
    expect($resultado->ifthenpay_request_id)->toBe('req-2');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/IfthenPayServiceTest.php`
Expected: FAIL — `Class "App\Services\IfthenPayService" not found`

- [ ] **Step 3: `app/Services/IfthenPayService.php`**

```php
<?php

namespace App\Services;

use App\Enums\PagamentoEstado;
use App\Enums\PagamentoMetodo;
use App\Models\Pagamento;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IfthenPayService
{
    private const MB_ENDPOINT = 'https://ifthenpay.com/api/multibanco/reference/init';
    private const MBWAY_ENDPOINT = 'https://ifthenpay.com/api/mbway/mb/wayrequest';

    public function gerarReferenciaMb(Pagamento $pagamento): Pagamento
    {
        $response = Http::asForm()->post(self::MB_ENDPOINT, [
            'mbKey' => config('services.ifthenpay.mb_key'),
            'orderId' => (string) $pagamento->orcamento_id,
            'amount' => number_format((float) $pagamento->valor, 2, '.', ''),
        ]);

        $body = $response->json();

        if (! $response->successful() || empty($body['Entidade']) || empty($body['Referencia'])) {
            throw new RuntimeException('Falha ao gerar referencia Multibanco: '.($body['Message'] ?? 'erro desconhecido'));
        }

        $pagamento->update([
            'metodo' => PagamentoMetodo::Mb,
            'estado' => PagamentoEstado::Pendente,
            'entidade' => $body['Entidade'],
            'referencia' => $body['Referencia'],
            'ifthenpay_request_id' => $body['RequestId'] ?? null,
            'telefone' => null,
            'expires_at' => now()->addHours(48),
        ]);

        return $pagamento->fresh();
    }

    public function gerarPedidoMbway(Pagamento $pagamento, string $telefone): Pagamento
    {
        $response = Http::asForm()->post(self::MBWAY_ENDPOINT, [
            'mbwaykey' => config('services.ifthenpay.mbway_key'),
            'orderid' => (string) $pagamento->orcamento_id,
            'amount' => number_format((float) $pagamento->valor, 2, '.', ''),
            'mobilenumber' => $telefone,
        ]);

        $body = $response->json();

        if (! $response->successful() || ($body['Status'] ?? null) !== '000') {
            throw new RuntimeException('Falha ao gerar pedido MB WAY: '.($body['Message'] ?? 'erro desconhecido'));
        }

        $pagamento->update([
            'metodo' => PagamentoMetodo::Mbway,
            'estado' => PagamentoEstado::Pendente,
            'entidade' => null,
            'referencia' => null,
            'telefone' => $telefone,
            'ifthenpay_request_id' => $body['RequestId'] ?? null,
            'expires_at' => now()->addHours(48),
        ]);

        return $pagamento->fresh();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/IfthenPayServiceTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/IfthenPayService.php tests/Feature/IfthenPayServiceTest.php
git commit -m "feat(financeiro): IfthenPayService gera referencia MB e pedido MBWay"
```

---

## Task 6: `PagamentoController::store` — cliente escolhe método de pagamento

**Files:**
- Create: `app/Http/Controllers/Tickets/PagamentoController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/PagamentoEndpointTest.php`

- [ ] **Step 1: Escrever teste que falha**

```php
<?php
// tests/Feature/PagamentoEndpointTest.php
use App\Enums\PagamentoEstado;
use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Enums\UserRole;
use App\Models\Cliente;
use App\Models\Orcamento;
use App\Models\Pagamento;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function criarOrcamentoAprovadoComPagamento(): array
{
    $clienteUser = User::factory()->create(['role' => UserRole::Cliente]);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Teste',
        'email' => $clienteUser->email,
        'telefone' => '912345678',
        'nif' => '123456789',
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
    $orcamento = Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => 'aprovado', 'decided_at' => now()]);
    $pagamento = Pagamento::create(['orcamento_id' => $orcamento->id, 'estado' => PagamentoEstado::Pendente, 'valor' => 45.50]);

    return [$clienteUser, $orcamento, $pagamento];
}

test('cliente escolhe mb e recebe referencia', function () {
    Http::fake([
        'ifthenpay.com/api/multibanco/reference/init' => Http::response([
            'Entidade' => '12345', 'Referencia' => '123456789', 'RequestId' => 'req-1',
        ], 200),
    ]);
    [$clienteUser, $orcamento] = criarOrcamentoAprovadoComPagamento();

    $response = $this->actingAs($clienteUser)->postJson("/api/cliente/orcamentos/{$orcamento->id}/pagamento", [
        'metodo' => 'mb',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('pagamento.entidade', '12345');
    $response->assertJsonPath('pagamento.referencia', '123456789');
});

test('cliente escolhe mbway sem telefone recebe 422', function () {
    [$clienteUser, $orcamento] = criarOrcamentoAprovadoComPagamento();

    $response = $this->actingAs($clienteUser)->postJson("/api/cliente/orcamentos/{$orcamento->id}/pagamento", [
        'metodo' => 'mbway',
    ]);

    $response->assertStatus(422);
});

test('pedido repetido para pagamento pendente nao expirado devolve o existente sem chamar ifthenpay', function () {
    Http::fake();
    [$clienteUser, $orcamento, $pagamento] = criarOrcamentoAprovadoComPagamento();
    $pagamento->update(['metodo' => 'mb', 'entidade' => '12345', 'referencia' => '999', 'expires_at' => now()->addHours(48)]);

    $response = $this->actingAs($clienteUser)->postJson("/api/cliente/orcamentos/{$orcamento->id}/pagamento", [
        'metodo' => 'mb',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('pagamento.referencia', '999');
    Http::assertNothingSent();
});

test('cliente de outro orcamento nao pode gerar pagamento', function () {
    [, $orcamento] = criarOrcamentoAprovadoComPagamento();
    $outroClienteUser = User::factory()->create(['role' => UserRole::Cliente]);
    Cliente::create(['user_id' => $outroClienteUser->id, 'nome' => 'Outro', 'email' => 'outro@example.com', 'telefone' => '913456789']);

    $response = $this->actingAs($outroClienteUser)->postJson("/api/cliente/orcamentos/{$orcamento->id}/pagamento", [
        'metodo' => 'mb',
    ]);

    $response->assertStatus(403);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/PagamentoEndpointTest.php`
Expected: FAIL — rota `/api/cliente/orcamentos/{orcamento}/pagamento` não existe (404)

- [ ] **Step 3: `app/Http/Controllers/Tickets/PagamentoController.php`**

```php
<?php

namespace App\Http\Controllers\Tickets;

use App\Enums\PagamentoEstado;
use App\Enums\PagamentoOrigem;
use App\Http\Controllers\Controller;
use App\Jobs\EmitirFacturaRecibo;
use App\Models\Orcamento;
use App\Services\IfthenPayService;
use Illuminate\Http\Request;

class PagamentoController extends Controller
{
    public function store(Request $request, Orcamento $orcamento, IfthenPayService $ifthenPay)
    {
        $cliente = $request->user()->cliente;
        abort_if($cliente === null || $orcamento->ticket->cliente_id !== $cliente->id, 403);

        $pagamento = $orcamento->pagamento;
        abort_if($pagamento === null, 409, 'Orcamento ainda nao foi aprovado.');

        if ($pagamento->estado === PagamentoEstado::Pago) {
            return response()->json(['pagamento' => $pagamento], 200);
        }

        if ($pagamento->estado === PagamentoEstado::Pendente && ! $pagamento->estaExpirado()) {
            return response()->json(['pagamento' => $pagamento], 200);
        }

        $data = $request->validate([
            'metodo' => ['required', 'in:mb,mbway'],
            'telefone' => ['required_if:metodo,mbway', 'nullable', 'string', 'max:20'],
        ]);

        $pagamento = $data['metodo'] === 'mb'
            ? $ifthenPay->gerarReferenciaMb($pagamento)
            : $ifthenPay->gerarPedidoMbway($pagamento, $data['telefone']);

        return response()->json(['pagamento' => $pagamento], 201);
    }

    public function marcarPago(Orcamento $orcamento)
    {
        $pagamento = $orcamento->pagamento;
        abort_if($pagamento === null, 409, 'Orcamento ainda nao foi aprovado.');
        abort_if($pagamento->estado === PagamentoEstado::Pago, 409, 'Pagamento ja confirmado.');

        $pagamento->update([
            'estado' => PagamentoEstado::Pago,
            'origem' => PagamentoOrigem::Manual,
            'paid_at' => now(),
        ]);

        EmitirFacturaRecibo::dispatch($pagamento->fresh());

        return response()->json(['pagamento' => $pagamento->fresh()]);
    }
}
```

- [ ] **Step 4: Adicionar rota em `routes/api.php`, dentro do grupo `role:cliente`**

```php
    Route::post('/orcamentos/{orcamento}/pagamento', [\App\Http\Controllers\Tickets\PagamentoController::class, 'store']);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/PagamentoEndpointTest.php`
Expected: FAIL ainda — `EmitirFacturaRecibo` não existe. Criar stub mínimo temporário (será substituído na Task 10):

```php
<?php
// app/Jobs/EmitirFacturaRecibo.php
namespace App\Jobs;

use App\Models\Pagamento;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EmitirFacturaRecibo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct(public Pagamento $pagamento)
    {
    }

    public function handle(): void
    {
    }
}
```

Run again: `php artisan test tests/Feature/PagamentoEndpointTest.php`
Expected: PASS (4 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Tickets/PagamentoController.php app/Jobs/EmitirFacturaRecibo.php routes/api.php tests/Feature/PagamentoEndpointTest.php
git commit -m "feat(financeiro): endpoint cliente escolhe metodo de pagamento"
```

---

## Task 7: Webhook IfthenPay — confirmação automática de pagamento

**Files:**
- Create: `app/Http/Controllers/Public/WebhookController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/WebhookIfthenpayTest.php`

- [ ] **Step 1: Escrever teste que falha**

```php
<?php
// tests/Feature/WebhookIfthenpayTest.php
use App\Enums\PagamentoEstado;
use App\Jobs\EmitirFacturaRecibo;
use App\Models\Pagamento;
use Illuminate\Support\Facades\Bus;

test('callback valido marca pagamento como pago e despacha job', function () {
    config(['services.ifthenpay.antiphishing_key' => 'chave-secreta']);
    Bus::fake();
    [, $orcamento] = criarOrcamentoAprovadoComPagamento();
    $pagamento = $orcamento->pagamento;
    $pagamento->update(['referencia' => '999888777']);

    $response = $this->postJson('/api/webhooks/ifthenpay', [
        'chave' => 'chave-secreta',
        'referencia' => '999888777',
        'requestid' => 'req-x',
    ]);

    $response->assertStatus(200);
    expect($pagamento->fresh()->estado->value)->toBe('pago');
    expect($pagamento->fresh()->origem->value)->toBe('ifthenpay');
    Bus::assertDispatched(EmitirFacturaRecibo::class);
});

test('callback com chave invalida e rejeitado e nao altera pagamento', function () {
    config(['services.ifthenpay.antiphishing_key' => 'chave-secreta']);
    [, $orcamento] = criarOrcamentoAprovadoComPagamento();
    $pagamento = $orcamento->pagamento;
    $pagamento->update(['referencia' => '999888777']);

    $response = $this->postJson('/api/webhooks/ifthenpay', [
        'chave' => 'chave-errada',
        'referencia' => '999888777',
    ]);

    $response->assertStatus(403);
    expect($pagamento->fresh()->estado->value)->toBe('pendente');
});

test('callback duplicado em pagamento ja pago e no-op', function () {
    config(['services.ifthenpay.antiphishing_key' => 'chave-secreta']);
    Bus::fake();
    [, $orcamento] = criarOrcamentoAprovadoComPagamento();
    $pagamento = $orcamento->pagamento;
    $pagamento->update(['referencia' => '999888777', 'estado' => PagamentoEstado::Pago, 'paid_at' => now()]);

    $response = $this->postJson('/api/webhooks/ifthenpay', [
        'chave' => 'chave-secreta',
        'referencia' => '999888777',
    ]);

    $response->assertStatus(200);
    Bus::assertNotDispatched(EmitirFacturaRecibo::class);
});
```

Nota: reutiliza a função `criarOrcamentoAprovadoComPagamento()` de `tests/Feature/PagamentoEndpointTest.php` — Pest partilha funções globais entre ficheiros de teste automaticamente.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/WebhookIfthenpayTest.php`
Expected: FAIL — rota `/api/webhooks/ifthenpay` não existe (404)

- [ ] **Step 3: `app/Http/Controllers/Public/WebhookController.php`**

```php
<?php

namespace App\Http\Controllers\Public;

use App\Enums\PagamentoEstado;
use App\Enums\PagamentoOrigem;
use App\Http\Controllers\Controller;
use App\Jobs\EmitirFacturaRecibo;
use App\Models\Pagamento;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function ifthenpay(Request $request)
    {
        abort_if($request->input('chave') !== config('services.ifthenpay.antiphishing_key'), 403);

        $pagamento = Pagamento::where('referencia', $request->input('referencia'))
            ->orWhere('ifthenpay_request_id', $request->input('requestid'))
            ->first();

        abort_if($pagamento === null, 404);

        if ($pagamento->estado === PagamentoEstado::Pago) {
            return response()->json(['ok' => true]);
        }

        $pagamento->update([
            'estado' => PagamentoEstado::Pago,
            'origem' => PagamentoOrigem::Ifthenpay,
            'paid_at' => now(),
        ]);

        EmitirFacturaRecibo::dispatch($pagamento->fresh());

        return response()->json(['ok' => true]);
    }
}
```

- [ ] **Step 4: Adicionar rota pública em `routes/api.php`** (fora de qualquer grupo `auth:sanctum`, junto à rota de convite)

```php
Route::post('/webhooks/ifthenpay', [\App\Http\Controllers\Public\WebhookController::class, 'ifthenpay']);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/WebhookIfthenpayTest.php`
Expected: PASS (3 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Public/WebhookController.php routes/api.php tests/Feature/WebhookIfthenpayTest.php
git commit -m "feat(financeiro): webhook ifthenpay confirma pagamento e despacha factura"
```

---

## Task 8: Marcação manual de pagamento (admin)

**Files:**
- Modify: `routes/api.php`
- Test: `tests/Feature/PagamentoMarcarPagoTest.php`

- [ ] **Step 1: Escrever teste que falha**

```php
<?php
// tests/Feature/PagamentoMarcarPagoTest.php
use App\Jobs\EmitirFacturaRecibo;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Bus;

test('admin marca pagamento como pago manualmente e despacha job', function () {
    Bus::fake();
    $admin = \App\Models\User::factory()->create(['role' => UserRole::Admin]);
    [, $orcamento] = criarOrcamentoAprovadoComPagamento();

    $response = $this->actingAs($admin)->postJson("/api/admin/orcamentos/{$orcamento->id}/pagamento/marcar-pago");

    $response->assertStatus(200);
    $response->assertJsonPath('pagamento.estado', 'pago');
    $response->assertJsonPath('pagamento.origem', 'manual');
    Bus::assertDispatched(EmitirFacturaRecibo::class);
});

test('tecnico nao pode marcar pagamento como pago', function () {
    $tecnico = \App\Models\User::factory()->create(['role' => UserRole::Tecnico]);
    [, $orcamento] = criarOrcamentoAprovadoComPagamento();

    $response = $this->actingAs($tecnico)->postJson("/api/admin/orcamentos/{$orcamento->id}/pagamento/marcar-pago");

    $response->assertStatus(403);
});

test('marcar pago duas vezes devolve 409 na segunda', function () {
    Bus::fake();
    $admin = \App\Models\User::factory()->create(['role' => UserRole::Admin]);
    [, $orcamento] = criarOrcamentoAprovadoComPagamento();

    $this->actingAs($admin)->postJson("/api/admin/orcamentos/{$orcamento->id}/pagamento/marcar-pago")->assertStatus(200);
    $response = $this->actingAs($admin)->postJson("/api/admin/orcamentos/{$orcamento->id}/pagamento/marcar-pago");

    $response->assertStatus(409);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/PagamentoMarcarPagoTest.php`
Expected: FAIL — rota `/api/admin/orcamentos/{orcamento}/pagamento/marcar-pago` não existe (404)

- [ ] **Step 3: Adicionar rota em `routes/api.php`, dentro do grupo `role:admin`**

```php
    Route::post('/orcamentos/{orcamento}/pagamento/marcar-pago', [\App\Http\Controllers\Tickets\PagamentoController::class, 'marcarPago']);
```

(o método `marcarPago` já foi implementado na Task 6)

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/PagamentoMarcarPagoTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add routes/api.php tests/Feature/PagamentoMarcarPagoTest.php
git commit -m "feat(financeiro): admin marca pagamento como pago manualmente"
```

---

## Task 9: `MoloniService` — OAuth2 e criação de Factura-Recibo

**Files:**
- Create: `app/Services/MoloniService.php`
- Test: `tests/Feature/MoloniServiceTest.php`

- [ ] **Step 1: Escrever teste que falha**

```php
<?php
// tests/Feature/MoloniServiceTest.php
use App\Models\MoloniCredential;
use App\Models\Pagamento;
use App\Services\MoloniService;
use Illuminate\Support\Facades\Http;

test('trocarCodigoPorToken grava access_token e refresh_token', function () {
    Http::fake([
        'api.moloni.pt/v1/grant/' => Http::response([
            'access_token' => 'token-1',
            'refresh_token' => 'refresh-1',
            'expires_in' => 3600,
        ], 200),
    ]);

    $credential = (new MoloniService)->trocarCodigoPorToken('codigo-123', 'https://api.oruidoscomputadores.pt/api/webhooks/moloni/callback');

    expect($credential->access_token)->toBe('token-1');
    expect($credential->refresh_token)->toBe('refresh-1');
    expect(MoloniCredential::count())->toBe(1);
});

test('garantirToken renova quando expirado', function () {
    MoloniCredential::create(['access_token' => 'antigo', 'refresh_token' => 'refresh-antigo', 'expires_at' => now()->subMinute()]);
    Http::fake([
        'api.moloni.pt/v1/grant/' => Http::response(['access_token' => 'novo', 'refresh_token' => 'refresh-novo', 'expires_in' => 3600], 200),
    ]);

    $credential = (new MoloniService)->garantirToken();

    expect($credential->access_token)->toBe('novo');
});

test('garantirToken nao renova quando ainda valido', function () {
    MoloniCredential::create(['access_token' => 'valido', 'refresh_token' => 'r', 'expires_at' => now()->addHour()]);
    Http::fake();

    $credential = (new MoloniService)->garantirToken();

    expect($credential->access_token)->toBe('valido');
    Http::assertNothingSent();
});

test('criarFacturaRecibo envia dados do cliente e itens e grava documento', function () {
    config(['fiscal.isento_iva' => true, 'fiscal.motivo_isencao' => 'Isento de IVA - artigo 53']);
    MoloniCredential::create(['access_token' => 'token-1', 'refresh_token' => 'r', 'expires_at' => now()->addHour()]);
    Http::fake([
        'api.moloni.pt/v1/invoiceReceipts/insert/*' => Http::response([
            'document_id' => 'doc-1',
            'document_set_name' => 'FR',
            'number' => '2026/1',
            'pdf_url' => 'https://moloni.pt/doc-1.pdf',
        ], 200),
    ]);

    [, $orcamento, $pagamento] = criarOrcamentoAprovadoComPagamento();
    $orcamento->itens()->create(['descricao' => 'Fonte', 'quantidade' => 1, 'preco_unitario' => 45.50]);

    $documento = (new MoloniService)->criarFacturaRecibo($pagamento->fresh());

    expect($documento['document_id'])->toBe('doc-1');
    expect($documento['numero_documento'])->toBe('FR 2026/1');
    expect($documento['pdf_url'])->toBe('https://moloni.pt/doc-1.pdf');

    Http::assertSent(function ($request) {
        return str_contains((string) $request->url(), 'invoiceReceipts/insert')
            && $request['customer_vat'] === '123456789';
    });
});
```

Nota: reutiliza `criarOrcamentoAprovadoComPagamento()` de `tests/Feature/PagamentoEndpointTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/MoloniServiceTest.php`
Expected: FAIL — `Class "App\Services\MoloniService" not found`

- [ ] **Step 3: `app/Services/MoloniService.php`**

```php
<?php

namespace App\Services;

use App\Models\MoloniCredential;
use App\Models\Pagamento;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MoloniService
{
    private const TOKEN_ENDPOINT = 'https://api.moloni.pt/v1/grant/';
    private const DOCUMENT_ENDPOINT = 'https://api.moloni.pt/v1/invoiceReceipts/insert/';

    public function trocarCodigoPorToken(string $code, string $redirectUri): MoloniCredential
    {
        $response = Http::asForm()->post(self::TOKEN_ENDPOINT, [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.moloni.client_id'),
            'client_secret' => config('services.moloni.client_secret'),
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);

        $body = $response->json();

        if (! $response->successful() || empty($body['access_token'])) {
            throw new RuntimeException('Falha ao trocar codigo Moloni por token.');
        }

        return MoloniCredential::updateOrCreate(['id' => 1], [
            'access_token' => $body['access_token'],
            'refresh_token' => $body['refresh_token'],
            'expires_at' => now()->addSeconds((int) $body['expires_in']),
        ]);
    }

    public function garantirToken(): MoloniCredential
    {
        $credential = MoloniCredential::first();
        abort_if($credential === null, 500, 'Moloni nao autorizado. Executar fluxo OAuth inicial.');

        if ($credential->expires_at->isFuture()) {
            return $credential;
        }

        $response = Http::asForm()->post(self::TOKEN_ENDPOINT, [
            'grant_type' => 'refresh_token',
            'client_id' => config('services.moloni.client_id'),
            'client_secret' => config('services.moloni.client_secret'),
            'refresh_token' => $credential->refresh_token,
        ]);

        $body = $response->json();

        if (! $response->successful() || empty($body['access_token'])) {
            throw new RuntimeException('Falha ao renovar token Moloni.');
        }

        $credential->update([
            'access_token' => $body['access_token'],
            'refresh_token' => $body['refresh_token'],
            'expires_at' => now()->addSeconds((int) $body['expires_in']),
        ]);

        return $credential->fresh();
    }

    public function criarFacturaRecibo(Pagamento $pagamento): array
    {
        $credential = $this->garantirToken();
        $orcamento = $pagamento->orcamento()->with('itens', 'ticket.cliente')->first();
        $cliente = $orcamento->ticket->cliente;

        $isento = (bool) config('fiscal.isento_iva');
        $taxa = $isento ? 0 : (int) config('fiscal.iva_taxa');

        $produtos = $orcamento->itens->map(fn ($item) => [
            'name' => $item->descricao,
            'qty' => $item->quantidade,
            'price' => (float) $item->preco_unitario,
            'taxes' => $isento ? [] : [['taxId' => (int) config('services.moloni.iva_tax_id'), 'value' => $taxa]],
            'exemptionReason' => $isento ? config('fiscal.motivo_isencao') : null,
        ])->all();

        $response = Http::asForm()->post(self::DOCUMENT_ENDPOINT, [
            'access_token' => $credential->access_token,
            'company_id' => config('services.moloni.company_id'),
            'date' => now()->format('Y-m-d'),
            'expiration_date' => now()->format('Y-m-d'),
            'customer_name' => $cliente->nome,
            'customer_vat' => $cliente->nif,
            'customer_address' => $cliente->morada,
            'products' => $produtos,
            'status' => 1,
        ]);

        $body = $response->json();

        if (! $response->successful() || empty($body['document_id'])) {
            throw new RuntimeException('Falha ao criar Factura-Recibo na Moloni: '.($body['message'] ?? 'erro desconhecido'));
        }

        return [
            'document_id' => (string) $body['document_id'],
            'numero_documento' => $body['document_set_name'].' '.$body['number'],
            'pdf_url' => $body['pdf_url'] ?? null,
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/MoloniServiceTest.php`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/MoloniService.php tests/Feature/MoloniServiceTest.php
git commit -m "feat(financeiro): MoloniService oauth e criacao de factura-recibo"
```

---

## Task 10: Job `EmitirFacturaRecibo` real + mail `FacturaEmitida`

**Files:**
- Modify: `app/Jobs/EmitirFacturaRecibo.php` (substituir o stub da Task 6)
- Create: `app/Mail/FacturaEmitida.php`
- Create: `resources/views/emails/factura-emitida.blade.php`
- Test: `tests/Feature/EmitirFacturaReciboJobTest.php`

- [ ] **Step 1: Escrever teste que falha**

```php
<?php
// tests/Feature/EmitirFacturaReciboJobTest.php
use App\Jobs\EmitirFacturaRecibo;
use App\Mail\FacturaEmitida;
use App\Models\MoloniCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

test('job cria documento na moloni grava id e envia email', function () {
    Mail::fake();
    MoloniCredential::create(['access_token' => 'token-1', 'refresh_token' => 'r', 'expires_at' => now()->addHour()]);
    Http::fake([
        'api.moloni.pt/v1/invoiceReceipts/insert/*' => Http::response([
            'document_id' => 'doc-1',
            'document_set_name' => 'FR',
            'number' => '2026/1',
            'pdf_url' => 'https://moloni.pt/doc-1.pdf',
        ], 200),
    ]);

    [, $orcamento, $pagamento] = criarOrcamentoAprovadoComPagamento();
    $orcamento->itens()->create(['descricao' => 'Fonte', 'quantidade' => 1, 'preco_unitario' => 45.50]);
    $pagamento->update(['estado' => 'pago', 'paid_at' => now()]);

    (new EmitirFacturaRecibo($pagamento->fresh()))->handle(new \App\Services\MoloniService);

    expect($pagamento->fresh()->moloni_document_id)->toBe('doc-1');
    expect($pagamento->fresh()->moloni_numero_documento)->toBe('FR 2026/1');
    Mail::assertSent(FacturaEmitida::class);
});

test('job falha e nao grava documento quando moloni indisponivel', function () {
    Mail::fake();
    MoloniCredential::create(['access_token' => 'token-1', 'refresh_token' => 'r', 'expires_at' => now()->addHour()]);
    Http::fake([
        'api.moloni.pt/v1/invoiceReceipts/insert/*' => Http::response(['message' => 'erro'], 500),
    ]);

    [, $orcamento, $pagamento] = criarOrcamentoAprovadoComPagamento();
    $orcamento->itens()->create(['descricao' => 'Fonte', 'quantidade' => 1, 'preco_unitario' => 45.50]);
    $pagamento->update(['estado' => 'pago', 'paid_at' => now()]);

    expect(fn () => (new EmitirFacturaRecibo($pagamento->fresh()))->handle(new \App\Services\MoloniService))
        ->toThrow(RuntimeException::class);

    expect($pagamento->fresh()->moloni_document_id)->toBeNull();
    expect($pagamento->fresh()->estado->value)->toBe('pago');
    Mail::assertNotSent(FacturaEmitida::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/EmitirFacturaReciboJobTest.php`
Expected: FAIL — `App\Mail\FacturaEmitida` não existe; job stub não faz nada.

- [ ] **Step 3: `app/Mail/FacturaEmitida.php`**

```php
<?php

namespace App\Mail;

use App\Models\Pagamento;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FacturaEmitida extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Pagamento $pagamento, public ?string $pdfUrl = null)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Factura-Recibo '.$this->pagamento->moloni_numero_documento.' - O Rui dos Computadores',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.factura-emitida',
            with: [
                'ticket' => $this->pagamento->orcamento->ticket,
                'pagamento' => $this->pagamento,
                'pdfUrl' => $this->pdfUrl,
            ],
        );
    }
}
```

- [ ] **Step 4: `resources/views/emails/factura-emitida.blade.php`**

```blade
<p>Ola {{ $ticket->cliente->nome }},</p>

<p>O pagamento do orcamento "{{ $ticket->titulo }}" foi confirmado. Segue a factura-recibo.</p>

<p><strong>Numero: {{ $pagamento->moloni_numero_documento }}</strong></p>
<p><strong>Valor: {{ number_format((float) $pagamento->valor, 2) }}EUR</strong></p>

@if($pdfUrl)
<p><a href="{{ $pdfUrl }}">Descarregar factura-recibo (PDF)</a></p>
@endif

<p>- O Rui dos Computadores</p>
```

- [ ] **Step 5: Substituir `app/Jobs/EmitirFacturaRecibo.php` inteiro**

```php
<?php

namespace App\Jobs;

use App\Mail\FacturaEmitida;
use App\Models\Pagamento;
use App\Services\MoloniService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class EmitirFacturaRecibo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(public Pagamento $pagamento)
    {
    }

    public function handle(MoloniService $moloni): void
    {
        $documento = $moloni->criarFacturaRecibo($this->pagamento);

        $this->pagamento->update([
            'moloni_document_id' => $documento['document_id'],
            'moloni_numero_documento' => $documento['numero_documento'],
        ]);

        $cliente = $this->pagamento->orcamento->ticket->cliente;

        if ($cliente->email) {
            Mail::to($cliente->email)->send(new FacturaEmitida($this->pagamento->fresh(), $documento['pdf_url']));
        }
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test tests/Feature/EmitirFacturaReciboJobTest.php`
Expected: PASS (2 tests)

- [ ] **Step 7: Run full suite**

Run: `php artisan test`
Expected: all passing, nenhuma regressão nas tasks anteriores (o stub da Task 6 é substituído mas a interface pública — construtor e `handle()` despachável — mantém-se compatível)

- [ ] **Step 8: Commit**

```bash
git add app/Jobs/EmitirFacturaRecibo.php app/Mail/FacturaEmitida.php resources/views/emails/factura-emitida.blade.php tests/Feature/EmitirFacturaReciboJobTest.php
git commit -m "feat(financeiro): job EmitirFacturaRecibo cria documento moloni e envia email"
```

---

## Task 11: Callback OAuth Moloni (setup inicial)

**Files:**
- Modify: `app/Http/Controllers/Public/WebhookController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/WebhookMoloniCallbackTest.php`

- [ ] **Step 1: Escrever teste que falha**

```php
<?php
// tests/Feature/WebhookMoloniCallbackTest.php
use App\Models\MoloniCredential;
use Illuminate\Support\Facades\Http;

test('callback moloni troca code por token e grava credencial', function () {
    Http::fake([
        'api.moloni.pt/v1/grant/' => Http::response([
            'access_token' => 'token-1',
            'refresh_token' => 'refresh-1',
            'expires_in' => 3600,
        ], 200),
    ]);

    $response = $this->getJson('/api/webhooks/moloni/callback?code=codigo-abc');

    $response->assertStatus(200);
    expect(MoloniCredential::count())->toBe(1);
    expect(MoloniCredential::first()->access_token)->toBe('token-1');
});

test('callback moloni sem code devolve 422', function () {
    $response = $this->getJson('/api/webhooks/moloni/callback');

    $response->assertStatus(422);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/WebhookMoloniCallbackTest.php`
Expected: FAIL — rota `/api/webhooks/moloni/callback` não existe (404)

- [ ] **Step 3: Adicionar método `moloniCallback` a `app/Http/Controllers/Public/WebhookController.php`**

Adicionar import `use App\Services\MoloniService;` e o método, depois de `ifthenpay()`:

```php
    public function moloniCallback(Request $request, MoloniService $moloni)
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $moloni->trocarCodigoPorToken($data['code'], config('app.url').'/api/webhooks/moloni/callback');

        return response()->json(['ok' => true]);
    }
```

- [ ] **Step 4: Adicionar rota em `routes/api.php`**

```php
Route::get('/webhooks/moloni/callback', [\App\Http\Controllers\Public\WebhookController::class, 'moloniCallback']);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/WebhookMoloniCallbackTest.php`
Expected: PASS (2 tests)

- [ ] **Step 6: Run full suite final**

Run: `php artisan test`
Expected: all passing

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Public/WebhookController.php routes/api.php tests/Feature/WebhookMoloniCallbackTest.php
git commit -m "feat(financeiro): callback oauth moloni para setup inicial"
```

---

## Task 12: `.env` de produção — variáveis novas (documentação, sem código)

**Files:**
- Modify: `README.md` (secção de variáveis de ambiente)

- [ ] **Step 1: Documentar no README as novas variáveis `.env` necessárias em produção**

```markdown
## Variáveis de ambiente — Financeiro

- `IFTHENPAY_MB_KEY`, `IFTHENPAY_MBWAY_KEY`, `IFTHENPAY_ANTIPHISHING_KEY`
- `MOLONI_CLIENT_ID`, `MOLONI_CLIENT_SECRET`, `MOLONI_COMPANY_ID`, `MOLONI_IVA_TAX_ID`
- `FISCAL_ISENTO_IVA` (bool, default true), `FISCAL_IVA_TAXA` (int, default 23), `FISCAL_MOTIVO_ISENCAO`

Setup inicial Moloni (uma vez, manual): abrir link de autorização OAuth da Moloni com `redirect_uri=https://api.oruidoscomputadores.pt/api/webhooks/moloni/callback`, autorizar, o callback troca o `code` por token automaticamente.
```

- [ ] **Step 2: Commit**

```bash
git add README.md
git commit -m "docs(financeiro): variaveis de ambiente e setup oauth moloni"
```

---

## Self-Review (feito pelo autor do plano)

**Cobertura da spec:**
- Requisito legal (sem factura antes de pagar) → Task 4 (Pagamento só criado, nunca documento fiscal) + Task 10 (job só corre após `estado=pago`). ✅
- Regime IVA configurável → Task 1 + uso em Task 9. ✅
- Tabela `pagamentos` + `moloni_credentials` → Task 3. ✅
- Enums → Task 2. ✅
- Fluxo passo 1 (NIF guard + criar Pagamento) → Task 4. ✅
- Fluxo passo 2 (escolher método, idempotência) → Task 6. ✅
- Fluxo passo 3 (webhook automático + manual admin) → Tasks 7, 8. ✅
- Fluxo passo 4 (job, retry, email) → Tasks 9, 10. ✅
- Fluxo passo 5 (expiração on-read) → Task 3 (`estaExpirado`/`estado_efetivo`). ✅
- Fluxo passo 6 (OAuth Moloni) → Task 11. ✅
- Erros da spec (tabela) → cobertos pelos testes de cada task (chave inválida 403, duplicado no-op, sem NIF 422, moloni em baixo → job falha sem gravar documento). ✅

**Placeholder scan:** nenhum "TBD"/"TODO" — todo o código é completo e executável.

**Type consistency:** `Pagamento::estaExpirado()`/`estado_efetivo` usados consistentemente nas Tasks 3, 6; `IfthenPayService::gerarReferenciaMb`/`gerarPedidoMbway` com mesma assinatura em Tasks 5 e 6; `MoloniService::criarFacturaRecibo` devolve sempre `['document_id', 'numero_documento', 'pdf_url']`, consumido igual em Task 10.
