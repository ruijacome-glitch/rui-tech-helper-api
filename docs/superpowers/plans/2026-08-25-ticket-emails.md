# Emails HTML profissionais — ticket lifecycle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Branded HTML layout (logo + cores) para todos os emails que o cliente recebe, incluindo um novo email de "ticket criado" que ainda não existe.

**Architecture:** Um layout Blade partilhado (`emails/layout.blade.php`) que os 4 templates client-facing passam a usar via `@extends`. `TicketEstado` ganha um método `label()` para PT amigável. Novo Mailable `TicketCriado` disparado no `Ticket::booted()` `created` event, mesmo padrão try/catch+`Log::error` já usado em `mudarEstado()`. Os dois sends que ainda não tinham try/catch (`OrcamentoController::store()`, `ClienteController::store()`) ganham-no nesta ronda.

**Tech Stack:** Laravel 11, Blade, Pest, Resend (mail driver já configurado).

---

### Task 1: Logo PNG + config de tracking URL

**Files:**
- Create: `public/images/logo-email.png`
- Modify: `config/services.php`
- Modify: `.env.example`

- [ ] **Step 1: Exportar o logo SVG para PNG**

Run:
```bash
"/c/Program Files/Inkscape/bin/inkscape" "../rui-tech-helper/src/assets/logo-rui.svg" --export-type=png --export-width=240 --export-filename="public/images/logo-email.png"
```
(caminho relativo à raiz de `rui-tech-helper-api`; ajustar se o Inkscape não estiver em `/c/Program Files/Inkscape/bin/inkscape`, procurar com `which inkscape` ou usar `"C:\Program Files\Inkscape\bin\inkscape.exe"` no PowerShell)

Expected: ficheiro `public/images/logo-email.png` criado, ~25-30KB, 240px de largura.

- [ ] **Step 2: Adicionar `tracking_url` ao config**

Em `config/services.php`, adicionar depois de `'frontend_url'`:

```php
    'frontend_url' => env('FRONTEND_PUBLIC_URL'),

    'tracking_url' => env('TRACKING_URL', 'https://tracking.oruidoscomputadores.pt'),

```

- [ ] **Step 3: Adicionar `TRACKING_URL` ao `.env.example`**

Em `.env.example`, junto à linha `FRONTEND_PUBLIC_URL=https://oruidoscomputadores.pt` (linha 38), adicionar por baixo:

```
TRACKING_URL=https://tracking.oruidoscomputadores.pt
```

- [ ] **Step 4: Commit**

```bash
git add public/images/logo-email.png config/services.php .env.example
git commit -m "feat: add email logo asset and tracking_url config"
```

---

### Task 2: `TicketEstado::label()`

**Files:**
- Modify: `app/Enums/TicketEstado.php`
- Test: `tests/Feature/TicketEstadoLabelTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/TicketEstadoLabelTest.php
use App\Enums\TicketEstado;

test('label devolve texto PT para cada estado', function () {
    expect(TicketEstado::Aberto->label())->toBe('Recebido');
    expect(TicketEstado::EmAnalise->label())->toBe('Em Diagnóstico');
    expect(TicketEstado::AguardaPeca->label())->toBe('Aguarda Peças');
    expect(TicketEstado::EmCurso->label())->toBe('Em Reparação');
    expect(TicketEstado::EmTestes->label())->toBe('Reparação Concluída');
    expect(TicketEstado::AguardaCliente->label())->toBe('Pronto p/ Levantamento');
    expect(TicketEstado::Resolvido->label())->toBe('Entregue');
    expect(TicketEstado::Cancelado->label())->toBe('Cancelado');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/TicketEstadoLabelTest.php`
Expected: FAIL — `Call to undefined method App\Enums\TicketEstado::label()`

- [ ] **Step 3: Implement `label()`**

In `app/Enums/TicketEstado.php`, add inside the enum body (after the `case` list):

```php
    public function label(): string
    {
        return match ($this) {
            self::Aberto => 'Recebido',
            self::EmAnalise => 'Em Diagnóstico',
            self::AguardaPeca => 'Aguarda Peças',
            self::EmCurso => 'Em Reparação',
            self::EmTestes => 'Reparação Concluída',
            self::AguardaCliente => 'Pronto p/ Levantamento',
            self::Resolvido => 'Entregue',
            self::Cancelado => 'Cancelado',
        };
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/TicketEstadoLabelTest.php`
Expected: PASS (8 assertions)

- [ ] **Step 5: Commit**

```bash
git add app/Enums/TicketEstado.php tests/Feature/TicketEstadoLabelTest.php
git commit -m "feat: add PT labels to TicketEstado enum"
```

---

### Task 3: Layout Blade partilhado

**Files:**
- Create: `resources/views/emails/layout.blade.php`

- [ ] **Step 1: Criar o layout**

```blade
{{-- resources/views/emails/layout.blade.php --}}
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('subject', 'O Rui dos Computadores')</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; padding: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; max-width:600px; width:100%; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td align="center" style="background-color:#0F1B2E; padding: 24px;">
                            <img src="{{ config('app.url') }}/images/logo-email.png" alt="O Rui dos Computadores" width="120" style="display:block;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px 24px; color:#1a1a1a; font-size:15px; line-height:1.6;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="background-color:#f4f4f5; padding: 16px 24px; color:#6b7280; font-size:12px;">
                            O Rui dos Computadores &middot; ola@oruidoscomputadores.pt
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
```

Nota: sem `<style>` de bloco — cores/espaçamento inline em cada template, seguindo este ficheiro como exemplo (compatibilidade Outlook).

- [ ] **Step 2: Commit**

```bash
git add resources/views/emails/layout.blade.php
git commit -m "feat: add shared HTML layout for client emails"
```

---

### Task 4: `TicketCriado`

**Files:**
- Create: `app/Mail/TicketCriado.php`
- Create: `resources/views/emails/ticket-criado.blade.php`
- Modify: `app/Models/Ticket.php`
- Test: `tests/Feature/TicketCriadoMailTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/TicketCriadoMailTest.php
use App\Enums\TicketCategoria;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Enums\UserRole;
use App\Mail\TicketCriado;
use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('criar ticket envia email TicketCriado quando cliente tem email', function () {
    Mail::fake();
    config(['services.tracking_url' => 'https://tracking.oruidoscomputadores.pt']);

    $clienteUser = User::factory()->create(['role' => UserRole::Cliente]);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Teste',
        'email' => 'cliente@example.com',
        'telefone' => '912345678',
    ]);

    $ticket = Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Computador nao arranca.',
    ]);

    Mail::assertSent(TicketCriado::class, fn ($mail) => $mail->hasTo('cliente@example.com')
        && $mail->ticket->is($ticket));
});

test('email TicketCriado contem titulo e link de tracking', function () {
    config(['services.tracking_url' => 'https://tracking.oruidoscomputadores.pt']);

    $clienteUser = User::factory()->create(['role' => UserRole::Cliente]);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Teste',
        'email' => 'cliente@example.com',
        'telefone' => '912345678',
    ]);

    $ticket = Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Computador nao arranca.',
    ]);

    $rendered = (new TicketCriado($ticket))->render();

    expect($rendered)->toContain('PC nao liga');
    expect($rendered)->toContain('https://tracking.oruidoscomputadores.pt/t/'.$ticket->tracking_token);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/TicketCriadoMailTest.php`
Expected: FAIL — `Class "App\Mail\TicketCriado" not found`

- [ ] **Step 3: Criar o Mailable**

```php
<?php
// app/Mail/TicketCriado.php
namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketCriado extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Ticket $ticket)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recebemos o teu pedido - O Rui dos Computadores',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-criado',
            with: [
                'ticket' => $this->ticket,
                'trackingUrl' => rtrim(config('services.tracking_url'), '/').'/t/'.$this->ticket->tracking_token,
            ],
        );
    }
}
```

- [ ] **Step 4: Criar a view**

```blade
{{-- resources/views/emails/ticket-criado.blade.php --}}
@extends('emails.layout')

@section('subject', 'Recebemos o teu pedido')

@section('content')
    <p>Olá {{ $ticket->cliente->nome }},</p>

    <p>Recebemos o teu pedido e já está no nosso sistema:</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; border-radius:6px; margin: 16px 0;">
        <tr>
            <td style="padding: 16px;">
                <p style="margin:0 0 8px 0; font-weight:bold; font-size:16px;">{{ $ticket->titulo }}</p>
                <p style="margin:0; color:#6b7280; font-size:13px;">
                    Categoria: {{ ucfirst($ticket->categoria->value) }} &middot; Prioridade: {{ ucfirst($ticket->prioridade->value) }}
                </p>
            </td>
        </tr>
    </table>

    <p>{{ $ticket->descricao }}</p>

    <p style="text-align:center; margin: 24px 0;">
        <a href="{{ $trackingUrl }}" style="background-color:#2E7FFF; color:#ffffff; text-decoration:none; padding: 12px 24px; border-radius:6px; font-weight:bold; display:inline-block;">Acompanhar pedido</a>
    </p>

    <p>- O Rui dos Computadores</p>
@endsection
```

- [ ] **Step 5: Disparar no `Ticket::booted()`**

In `app/Models/Ticket.php`, modify the `booted()` method:

```php
    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket) {
            $ticket->tracking_token ??= (string) Str::uuid();
        });

        static::created(function (Ticket $ticket) {
            if ($ticket->cliente->email) {
                try {
                    Mail::to($ticket->cliente->email)->send(new TicketCriado($ticket));
                } catch (\Throwable $e) {
                    Log::error('Falha a enviar email de ticket criado', [
                        'ticket_id' => $ticket->id,
                        'erro' => $e->getMessage(),
                    ]);
                }
            }
        });
    }
```

Add the import at the top of `app/Models/Ticket.php` (alongside the existing `use App\Mail\TicketEstadoAlterado;`):

```php
use App\Mail\TicketCriado;
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/TicketCriadoMailTest.php`
Expected: PASS (2 tests)

- [ ] **Step 7: Run full suite to check for regressions**

Run: `vendor/bin/pest`
Expected: all passing (every existing `Ticket::create(...)` call in the suite now also fires this event — `Mail::fake()` in other tests already prevents real sends, but check for any test asserting `Mail::assertNothingSent()` after a plain `Ticket::create()` without a cliente email, which would still hold since the guard is `if ($ticket->cliente->email)`)

- [ ] **Step 8: Commit**

```bash
git add app/Mail/TicketCriado.php resources/views/emails/ticket-criado.blade.php app/Models/Ticket.php tests/Feature/TicketCriadoMailTest.php
git commit -m "feat: send branded email when a ticket is created"
```

---

### Task 5: Redesign `ticket-estado-alterado`

**Files:**
- Modify: `resources/views/emails/ticket-estado-alterado.blade.php`
- Modify: `app/Mail/TicketEstadoAlterado.php`
- Test: `tests/Feature/TicketMudarEstadoEmailTest.php` (extend)

- [ ] **Step 1: Write the failing test (append to existing file)**

Add to `tests/Feature/TicketMudarEstadoEmailTest.php` (keep existing tests, add these two):

```php
test('email de mudanca de estado mostra label PT e link de tracking', function () {
    config(['services.tracking_url' => 'https://tracking.oruidoscomputadores.pt']);

    $clienteUser = User::factory()->create(['role' => UserRole::Cliente]);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Teste',
        'email' => 'cliente@example.com',
        'telefone' => '912345678',
    ]);
    $ticket = Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Computador nao arranca.',
    ]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $evento = $ticket->mudarEstado($admin, TicketEstado::EmAnalise);

    $rendered = (new App\Mail\TicketEstadoAlterado($evento))->render();

    expect($rendered)->toContain('Em Diagnóstico');
    expect($rendered)->toContain('https://tracking.oruidoscomputadores.pt/t/'.$ticket->tracking_token);
    expect($rendered)->not->toContain('cancelado ');
});

test('email de cancelamento mostra faixa de aviso', function () {
    $clienteUser = User::factory()->create(['role' => UserRole::Cliente]);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Teste',
        'email' => 'cliente@example.com',
        'telefone' => '912345678',
    ]);
    $ticket = Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Computador nao arranca.',
    ]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $evento = $ticket->mudarEstado($admin, TicketEstado::Cancelado);

    $rendered = (new App\Mail\TicketEstadoAlterado($evento))->render();

    expect($rendered)->toContain('Este ticket foi cancelado');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/TicketMudarEstadoEmailTest.php`
Expected: FAIL — asserts fail since template still shows raw enum value and has no layout/link

- [ ] **Step 3: Update the Mailable**

Replace `app/Mail/TicketEstadoAlterado.php` content method to pass `trackingUrl`:

```php
<?php
// app/Mail/TicketEstadoAlterado.php
namespace App\Mail;

use App\Models\TicketEvento;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketEstadoAlterado extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TicketEvento $evento)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Atualizacao do seu pedido - O Rui dos Computadores',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-estado-alterado',
            with: [
                'ticket' => $this->evento->ticket,
                'evento' => $this->evento,
                'trackingUrl' => rtrim(config('services.tracking_url'), '/').'/t/'.$this->evento->ticket->tracking_token,
            ],
        );
    }
}
```

- [ ] **Step 4: Update the view**

```blade
{{-- resources/views/emails/ticket-estado-alterado.blade.php --}}
@extends('emails.layout')

@section('subject', 'Atualização do seu pedido')

@section('content')
    <p>Olá {{ $ticket->cliente->nome }},</p>

    @if($evento->estado_novo->value === 'cancelado')
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#fee2e2; border-radius:6px; margin: 0 0 16px 0;">
            <tr>
                <td style="padding: 12px 16px; color:#991b1b; font-weight:bold; font-size:14px;">
                    Este ticket foi cancelado
                </td>
            </tr>
        </table>
    @endif

    <p>O estado do teu pedido "{{ $ticket->titulo }}" foi actualizado para:</p>

    <p style="text-align:center; margin: 16px 0;">
        <span style="background-color:#2E7FFF; color:#ffffff; padding: 8px 16px; border-radius:20px; font-weight:bold; font-size:14px; display:inline-block;">{{ $evento->estado_novo->label() }}</span>
    </p>

    @if($evento->observacao_visivel_cliente && $evento->observacao)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; border-radius:6px; margin: 16px 0;">
            <tr>
                <td style="padding: 12px 16px; font-size:14px; color:#374151;">
                    {{ $evento->observacao }}
                </td>
            </tr>
        </table>
    @endif

    <p style="text-align:center; margin: 24px 0;">
        <a href="{{ $trackingUrl }}" style="background-color:#0F1B2E; color:#ffffff; text-decoration:none; padding: 12px 24px; border-radius:6px; font-weight:bold; display:inline-block;">Acompanhar pedido</a>
    </p>

    <p>- O Rui dos Computadores</p>
@endsection
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/TicketMudarEstadoEmailTest.php`
Expected: PASS (4 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Mail/TicketEstadoAlterado.php resources/views/emails/ticket-estado-alterado.blade.php tests/Feature/TicketMudarEstadoEmailTest.php
git commit -m "feat: redesign ticket-estado-alterado email with branded layout"
```

---

### Task 6: Redesign `orcamento-pronto` + try/catch fix

**Files:**
- Modify: `resources/views/emails/orcamento-pronto.blade.php`
- Modify: `app/Http/Controllers/Tickets/OrcamentoController.php`
- Test: `tests/Feature/OrcamentoProntoMailTest.php` (extend)

- [ ] **Step 1: Confirmed current state of `OrcamentoController::store()`**

O bloco relevante (linhas ~40-48) actual:

```php
        $orcamento->load('itens');

        if ($ticket->cliente->email) {
            Mail::to($ticket->cliente->email)->send(new OrcamentoPronto($orcamento));
        }

        return response()->json(['orcamento' => $orcamento], 201);
```

- [ ] **Step 2: Write the failing test (append to existing file)**

Add to `tests/Feature/OrcamentoProntoMailTest.php`:

```php
test('email de orcamento pronto usa layout com botao', function () {
    config(['services.frontend_url' => 'https://oruidoscomputadores.pt']);

    $clienteUser = User::factory()->create(['role' => UserRole::Cliente]);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Teste',
        'email' => 'cliente@example.com',
        'telefone' => '912345678',
    ]);
    $ticket = Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Computador nao arranca.',
    ]);
    $orcamento = Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => OrcamentoEstado::Pendente]);
    $orcamento->itens()->create(['descricao' => 'Fonte', 'quantidade' => 1, 'preco_unitario' => 50.00]);

    $rendered = (new OrcamentoPronto($orcamento->fresh('itens')))->render();

    expect($rendered)->toContain('Ver e aprovar orçamento');
    expect($rendered)->toContain('O Rui dos Computadores');
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/OrcamentoProntoMailTest.php`
Expected: FAIL — texto do botão ainda não existe no template actual

- [ ] **Step 4: Update the view**

```blade
{{-- resources/views/emails/orcamento-pronto.blade.php --}}
@extends('emails.layout')

@section('subject', 'Orçamento pronto para aprovação')

@section('content')
    <p>Olá {{ $ticket->cliente->nome }},</p>

    <p>Já temos orçamento pronto para o teu pedido "{{ $ticket->titulo }}":</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin: 16px 0;">
        @foreach($orcamento->itens as $item)
            <tr>
                <td style="padding: 8px 0; border-bottom:1px solid #e5e7eb; font-size:14px;">{{ $item->descricao }} &times; {{ $item->quantidade }}</td>
                <td style="padding: 8px 0; border-bottom:1px solid #e5e7eb; font-size:14px; text-align:right;">{{ number_format((float) $item->preco_unitario, 2) }}€</td>
            </tr>
        @endforeach
        <tr>
            <td style="padding: 12px 0 0 0; font-weight:bold; font-size:16px;">Total</td>
            <td style="padding: 12px 0 0 0; font-weight:bold; font-size:16px; text-align:right;">{{ number_format($total, 2) }}€</td>
        </tr>
    </table>

    <p style="text-align:center; margin: 24px 0;">
        <a href="{{ $portalUrl }}" style="background-color:#2E7FFF; color:#ffffff; text-decoration:none; padding: 12px 24px; border-radius:6px; font-weight:bold; display:inline-block;">Ver e aprovar orçamento</a>
    </p>

    <p>- O Rui dos Computadores</p>
@endsection
```

- [ ] **Step 5: Add try/catch to the controller**

In `app/Http/Controllers/Tickets/OrcamentoController.php`, replace:

```php
        if ($ticket->cliente->email) {
            Mail::to($ticket->cliente->email)->send(new OrcamentoPronto($orcamento));
        }
```

with:

```php
        if ($ticket->cliente->email) {
            try {
                Mail::to($ticket->cliente->email)->send(new OrcamentoPronto($orcamento));
            } catch (\Throwable $e) {
                Log::error('Falha a enviar email de orcamento pronto', [
                    'orcamento_id' => $orcamento->id,
                    'erro' => $e->getMessage(),
                ]);
            }
        }
```

`Mail` is already imported in this file (`use Illuminate\Support\Facades\Mail;`). Add `use Illuminate\Support\Facades\Log;` alongside it if not already present.

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/OrcamentoProntoMailTest.php`
Expected: PASS (2 tests)

- [ ] **Step 7: Commit**

```bash
git add resources/views/emails/orcamento-pronto.blade.php app/Http/Controllers/Tickets/OrcamentoController.php tests/Feature/OrcamentoProntoMailTest.php
git commit -m "feat: redesign orcamento-pronto email, guard mail send with try/catch"
```

---

### Task 7: Redesign `convite-cliente` + try/catch fix

**Files:**
- Modify: `resources/views/emails/convite-cliente.blade.php`
- Modify: `app/Http/Controllers/Admin/ClienteController.php`
- Test: `tests/Feature/Admin/CreateClienteTest.php` (extend, or create if this test file doesn't exist — check `tests/Feature/Admin/` first)

- [ ] **Step 1: Confirmed current state of `ClienteController::store()`**

Lido por completo (linhas 1-76). Bloco relevante (linha 72, sem try/catch):

```php
        $plaintextToken = Str::random(64);
        Convite::create([
            'cliente_id' => $cliente->id,
            'token_hash' => hash('sha256', $plaintextToken),
            'expires_at' => now()->addDays(7),
        ]);

        Mail::to($cliente->email)->send(new ConviteCliente($convite, $plaintextToken));

        return response()->json(['cliente' => $cliente], 201);
```

Nota para quem implementar: confirmar o nome exacto da variável `$convite` vs o retorno de `Convite::create(...)` ao editar — no código actual pode não estar atribuída a uma variável (`Convite::create([...])` sem `$convite =`); se for o caso, adicionar a atribuição como parte deste fix.

- [ ] **Step 2: Write the failing test**

Primeiro, correr `Glob` em `tests/Feature/Admin/*.php` para confirmar se já existe um teste de criação de cliente e o seu padrão exacto de setup. Se existir `CreateClienteTest.php` (ou nome semelhante), adicionar o teste abaixo ao ficheiro; caso contrário criar `tests/Feature/Admin/CreateClienteTest.php` com o boilerplate padrão (`use Tests\TestCase; uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);` conforme o resto do projecto) mais este teste:

```php
test('email de convite usa layout com botao de activacao', function () {
    Mail::fake();

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->postJson('/api/admin/clientes', [
        'nome' => 'Cliente Novo',
        'email' => 'novo@example.com',
        'telefone' => '912345678',
    ]);

    Mail::assertSent(ConviteCliente::class, function ($mail) {
        $rendered = $mail->render();

        return str_contains($rendered, 'Ativar conta') && str_contains($rendered, 'O Rui dos Computadores');
    });
});
```

Add `use App\Mail\ConviteCliente;`, `use App\Enums\UserRole;`, `use App\Models\User;` and `use Illuminate\Support\Facades\Mail;` to the test file's imports if not already present.

- [ ] **Step 3: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/Admin/CreateClienteTest.php`
Expected: FAIL — botão "Ativar conta" ainda não existe no template actual (é um link cru)

- [ ] **Step 4: Update the view**

```blade
{{-- resources/views/emails/convite-cliente.blade.php --}}
@extends('emails.layout')

@section('subject', 'Ativa a tua conta')

@section('content')
    <p>Olá {{ $cliente->nome }},</p>

    <p>Foi criada uma ficha para ti no sistema d'O Rui dos Computadores. Para veres o estado das tuas intervenções e definires a tua password, clica no botão abaixo:</p>

    <p style="text-align:center; margin: 24px 0;">
        <a href="{{ $url }}" style="background-color:#2E7FFF; color:#ffffff; text-decoration:none; padding: 12px 24px; border-radius:6px; font-weight:bold; display:inline-block;">Ativar conta</a>
    </p>

    <p style="color:#6b7280; font-size:13px;">Este link expira em 7 dias.</p>

    <p>- O Rui dos Computadores</p>
@endsection
```

- [ ] **Step 5: Add try/catch to the controller**

In `app/Http/Controllers/Admin/ClienteController.php`, ensure `Convite::create(...)` result is captured in `$convite` (add the assignment if missing), then replace:

```php
        Mail::to($cliente->email)->send(new ConviteCliente($convite, $plaintextToken));
```

with:

```php
        if ($cliente->email) {
            try {
                Mail::to($cliente->email)->send(new ConviteCliente($convite, $plaintextToken));
            } catch (\Throwable $e) {
                Log::error('Falha a enviar email de convite', [
                    'cliente_id' => $cliente->id,
                    'erro' => $e->getMessage(),
                ]);
            }
        }
```

Add `use Illuminate\Support\Facades\Mail;` and `use Illuminate\Support\Facades\Log;` to the top of `ClienteController.php` if not already present.

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Admin/CreateClienteTest.php`
Expected: PASS

- [ ] **Step 7: Run full suite**

Run: `vendor/bin/pest`
Expected: all tests passing

- [ ] **Step 8: Commit**

```bash
git add resources/views/emails/convite-cliente.blade.php app/Http/Controllers/Admin/ClienteController.php tests/Feature/Admin/CreateClienteTest.php
git commit -m "feat: redesign convite-cliente email, guard mail send with try/catch"
```

---

### Task 8: Manual preview verification

**Files:** none (verification only)

- [ ] **Step 1: Preview each of the 4 templates in browser**

Run (from `rui-tech-helper-api` root, with `php artisan tinker`):

```php
file_put_contents('storage/app/preview.html', (new App\Mail\TicketCriado(App\Models\Ticket::with('cliente')->first()))->render());
```

Repeat for `TicketEstadoAlterado` (needs a `TicketEvento` instance), `OrcamentoPronto` (needs an `Orcamento` with `itens`), `ConviteCliente` (needs a `Convite` + plaintext token) — or use the Pest tests' `render()` calls as a quicker source of rendered HTML if seeding tinker data is slow.

Expected: opening `storage/app/preview.html` in a browser shows navy header with logo, blue CTA button, readable body, footer — for each of the 4 templates. Confirm `cancelado` state shows the red warning banner (Task 5 test already covers this programmatically, but a visual check catches spacing/contrast issues no test would).

- [ ] **Step 2: No commit needed** (verification step only, `storage/app/preview.html` is gitignored)
