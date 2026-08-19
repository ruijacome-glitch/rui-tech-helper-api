# Fundação Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up the Laravel API (`rui-tech-helper-api`) with the DB schema, 3-role authentication (admin/tecnico/cliente), invite-based client onboarding, and a cPanel-git deploy pipeline that works without SSH.

**Architecture:** Laravel app with `vendor/` committed to git (no server-side composer possible). Sanctum SPA cookie auth shared across `api.oruidoscomputadores.pt` and `crm.oruidoscomputadores.pt` (same registrable domain). Three tables beyond Laravel defaults: `clientes`, `convites`, plus a `role` enum column on `users`. Migrations run locally against the production MySQL (remote-access whitelisted IP) since there's no way to run `artisan` on the server.

**Tech Stack:** Laravel (latest stable, PHP 8.5), Sanctum, MySQL, Resend (mail), Pest for tests.

**Reference spec:** `docs/superpowers/specs/2026-08-19-fundacao-backend-design.md`

---

## Prerequisites (once, before Task 1)

Máquina sem PHP/Composer em `PATH` ainda — confirmado via `php -v` → command not found. Comandos Laravel deste plano correm **localmente**, não no servidor (sem SSH lá), logo isto tem que ficar resolvido primeiro.

- [ ] **Instalar PHP 8.5** (igual à versão do MultiPHP Selector no cPanel) — via [windows.php.net](https://windows.php.net/download/) ou bundle tipo XAMPP/Laragon. Adicionar dir do PHP ao `PATH`.
- [ ] **Activar extensões**: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `fileinfo`, `curl` no `php.ini`.
- [ ] **Verificar Composer** — `composer -V` já resolve para `/c/ProgramData/ComposerSetup/bin/composer`, só precisa de `php` no `PATH`. Repetir `composer -V` depois de instalar PHP; esperar string de versão, sem erro.
- [ ] **Verificar**: `php -v` mostra `PHP 8.5.x`.

---

### Task 1: Laravel project bootstrap

**Files:**
- Create: entire Laravel skeleton under `d:/Projectos/o Rui dos Computadores/assets/rui-tech-helper-api/` (alongside the existing `docs/` folder)
- Modify: `.gitignore` (remove the default `vendor/` ignore — we commit it)

- [ ] **Step 1: Scaffold the app**

Run from `d:/Projectos/o Rui dos Computadores/assets/rui-tech-helper-api`:

```bash
composer create-project laravel/laravel . --prefer-dist
```

Installs into current dir (already git-initialized with spec doc committed). Expected: `composer.json`, `app/`, `routes/`, `database/`, `vendor/`, `.env.example`, default `.gitignore` created.

- [ ] **Step 2: Un-ignore `vendor/`**

Open `.gitignore`, remove line `/vendor`. Confirm:

```bash
grep -n vendor .gitignore
```

Expected: no output.

- [ ] **Step 3: App key + base config**

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```
APP_NAME="Rui dos Computadores API"
APP_URL=https://api.oruidoscomputadores.pt
DB_CONNECTION=mysql
DB_HOST=<host do MySQL remoto do cPanel>
DB_PORT=3306
DB_DATABASE=<nome da BD de produção>
DB_USERNAME=<utilizador MySQL>
DB_PASSWORD=<password MySQL>
```

(Credenciais vêm do cPanel → MySQL Databases / Remote MySQL, preenchidas à mão — nunca commitar `.env`.)

- [ ] **Step 4: Verify boot**

```bash
php artisan serve
```

Expected: `Server running on [http://127.0.0.1:8000]`. Noutro terminal: `curl http://127.0.0.1:8000` → HTML de boas-vindas Laravel. Parar servidor (Ctrl+C).

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore: bootstrap Laravel project, vendor committed"
```

---

### Task 2: Sanctum SPA auth + CORS for the two subdomains

**Files:**
- Modify: `config/sanctum.php`, `config/cors.php`, `.env`, `bootstrap/app.php`

- [ ] **Step 1: Install Sanctum**

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

Expected: `config/sanctum.php` e migração `create_personal_access_tokens_table` aparecem em `database/migrations/`.

- [ ] **Step 2: Configure stateful domains**

`.env`:

```
SESSION_DOMAIN=.oruidoscomputadores.pt
SANCTUM_STATEFUL_DOMAINS=crm.oruidoscomputadores.pt,api.oruidoscomputadores.pt,localhost,localhost:5173,127.0.0.1:5173
FRONTEND_URL=https://crm.oruidoscomputadores.pt
```

Confirmar que `config/sanctum.php` lê `SANCTUM_STATEFUL_DOMAINS` da env (`'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', ...))`).

- [ ] **Step 3: Enable CORS with credentials**

`config/cors.php`:

```php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:5173')],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
```

- [ ] **Step 4: Register Sanctum's stateful middleware**

`bootstrap/app.php`, dentro de `->withMiddleware(function (Middleware $middleware) { ... })`:

```php
$middleware->statefulApi();
```

- [ ] **Step 5: Verify**

```bash
php artisan config:clear
php artisan route:list --path=sanctum
```

Expected: rota `sanctum/csrf-cookie` listada.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: configure Sanctum SPA auth and CORS for crm/api subdomains"
```

---

### Task 3: `role` enum on `users`

**Files:**
- Create: `app/Enums/UserRole.php`
- Modify: `database/migrations/0001_01_01_000000_create_users_table.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/UserRoleTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/UserRoleTest.php

use App\Enums\UserRole;
use App\Models\User;

test('user role casts to the UserRole enum', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    expect($user->fresh()->role)->toBe(UserRole::Admin);
});

test('user role defaults to cliente when not specified', function () {
    $user = User::factory()->create();

    expect($user->fresh()->role)->toBe(UserRole::Cliente);
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=UserRoleTest
```

Expected: FAIL — `Class "App\Enums\UserRole" not found`.

- [ ] **Step 3: Create the enum**

```php
<?php
// app/Enums/UserRole.php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Tecnico = 'tecnico';
    case Cliente = 'cliente';
}
```

- [ ] **Step 4: Add the migration column**

`database/migrations/0001_01_01_000000_create_users_table.php`, dentro de `up()`, depois da linha `email_verified_at`:

```php
$table->string('role')->default('cliente');
```

- [ ] **Step 5: Wire the cast on the model**

`app/Models/User.php`:

```php
<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }
}
```

- [ ] **Step 6: Run tests**

```bash
php artisan test --filter=UserRoleTest
```

Expected: PASS (2 tests). Confirmar `phpunit.xml` já tem `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` (default Laravel para testes).

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: add role enum to users"
```

---

### Task 4: `clientes` table + model

**Files:**
- Create: `database/migrations/xxxx_xx_xx_create_clientes_table.php`
- Create: `app/Models/Cliente.php`
- Test: `tests/Feature/ClienteModelTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/ClienteModelTest.php

use App\Enums\UserRole;
use App\Models\Cliente;
use App\Models\User;

test('cliente can exist without a linked user', function () {
    $cliente = Cliente::create(['nome' => 'Ana Silva', 'telefone' => '912345678']);

    expect($cliente->user_id)->toBeNull();
});

test('cliente can be linked to a user account', function () {
    $user = User::factory()->create(['role' => UserRole::Cliente]);
    $cliente = Cliente::create(['nome' => 'Ana Silva', 'telefone' => '912345678', 'user_id' => $user->id]);

    expect($cliente->fresh()->user->is($user))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=ClienteModelTest
```

Expected: FAIL — `Class "App\Models\Cliente" not found`.

- [ ] **Step 3: Create the migration**

```bash
php artisan make:migration create_clientes_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nome');
            $table->string('telefone')->nullable();
            $table->string('email')->nullable();
            $table->string('morada')->nullable();
            $table->string('nif', 9)->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
```

- [ ] **Step 4: Create the model**

```php
<?php
// app/Models/Cliente.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cliente extends Model
{
    protected $fillable = ['user_id', 'nome', 'telefone', 'email', 'morada', 'nif', 'notas'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --filter=ClienteModelTest
```

Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add clientes table and model"
```

---

### Task 5: `convites` table + model (hashed token)

**Files:**
- Create: `database/migrations/xxxx_xx_xx_create_convites_table.php`
- Create: `app/Models/Convite.php`
- Test: `tests/Feature/ConviteModelTest.php`

Token guardado **hashed** (`token_hash`), nunca em texto simples — mesmo padrão dos password-reset tokens do Laravel. O token em texto simples só aparece no link do email.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/ConviteModelTest.php

use App\Models\Cliente;
use App\Models\Convite;

test('convite belongs to a cliente', function () {
    $cliente = Cliente::create(['nome' => 'Ana Silva']);
    $convite = Convite::create([
        'cliente_id' => $cliente->id,
        'token_hash' => hash('sha256', 'plaintext-token'),
        'expires_at' => now()->addDays(7),
    ]);

    expect($convite->fresh()->cliente->is($cliente))->toBeTrue();
});

test('convite is expired after expires_at', function () {
    $cliente = Cliente::create(['nome' => 'Ana Silva']);
    $convite = Convite::create([
        'cliente_id' => $cliente->id,
        'token_hash' => hash('sha256', 'plaintext-token'),
        'expires_at' => now()->subDay(),
    ]);

    expect($convite->isExpired())->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=ConviteModelTest
```

Expected: FAIL — `Class "App\Models\Convite" not found`.

- [ ] **Step 3: Create the migration**

```bash
php artisan make:migration create_convites_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convites');
    }
};
```

- [ ] **Step 4: Create the model**

```php
<?php
// app/Models/Convite.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Convite extends Model
{
    protected $fillable = ['cliente_id', 'token_hash', 'expires_at', 'used_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }
}
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --filter=ConviteModelTest
```

Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add convites table and model with hashed tokens"
```

---

### Task 6: Login / logout / me endpoints (admin & tecnico)

**Files:**
- Create: `app/Http/Controllers/Auth/SessionController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Auth/LoginTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Auth/LoginTest.php

use App\Enums\UserRole;
use App\Models\User;

test('admin can log in with correct credentials', function () {
    $user = User::factory()->create([
        'email' => 'rui@oruidoscomputadores.pt',
        'password' => 'senha-segura-123',
        'role' => UserRole::Admin,
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'rui@oruidoscomputadores.pt',
        'password' => 'senha-segura-123',
    ]);

    $response->assertOk()->assertJsonPath('user.role', 'admin');
    $this->assertAuthenticatedAs($user);
});

test('login fails with wrong password', function () {
    User::factory()->create(['email' => 'rui@oruidoscomputadores.pt', 'password' => 'senha-segura-123']);

    $response = $this->postJson('/api/login', [
        'email' => 'rui@oruidoscomputadores.pt',
        'password' => 'errada',
    ]);

    $response->assertStatus(422);
    $this->assertGuest();
});

test('authenticated user can fetch their own profile', function () {
    $user = User::factory()->create(['role' => UserRole::Tecnico]);

    $response = $this->actingAs($user)->getJson('/api/me');

    $response->assertOk()->assertJsonPath('role', 'tecnico');
});

test('logout clears the session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/logout')->assertNoContent();
    $this->assertGuest();
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=LoginTest
```

Expected: FAIL — rota `/api/login` não existe (404).

- [ ] **Step 3: Create the controller**

```php
<?php
// app/Http/Controllers/Auth/SessionController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json(['user' => $this->serialize($request->user())]);
    }

    public function me(Request $request)
    {
        return response()->json($this->serialize($request->user()));
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    private function serialize(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
        ];
    }
}
```

- [ ] **Step 4: Register routes**

`routes/api.php`:

```php
<?php

use App\Http\Controllers\Auth\SessionController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [SessionController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [SessionController::class, 'me']);
    Route::post('/logout', [SessionController::class, 'destroy']);
});
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --filter=LoginTest
```

Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: login, logout and me endpoints via Sanctum"
```

---

### Task 7: Role middleware + route group skeletons

**Files:**
- Create: `app/Http/Middleware/EnsureUserHasRole.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Auth/RoleMiddlewareTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Auth/RoleMiddlewareTest.php

use App\Enums\UserRole;
use App\Models\User;

test('tecnico cannot access admin-only routes', function () {
    $tecnico = User::factory()->create(['role' => UserRole::Tecnico]);

    $this->actingAs($tecnico)->getJson('/api/admin/ping')->assertForbidden();
});

test('admin can access admin-only routes', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->getJson('/api/admin/ping')->assertOk();
});

test('cliente can access cliente routes', function () {
    $cliente = User::factory()->create(['role' => UserRole::Cliente]);

    $this->actingAs($cliente)->getJson('/api/cliente/ping')->assertOk();
});

test('guest is unauthorized on protected routes', function () {
    $this->getJson('/api/admin/ping')->assertUnauthorized();
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=RoleMiddlewareTest
```

Expected: FAIL — rotas não existem (404).

- [ ] **Step 3: Create the middleware**

```php
<?php
// app/Http/Middleware/EnsureUserHasRole.php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if ($request->user()?->role !== UserRole::from($role)) {
            abort(403, 'Sem permissão para aceder a este recurso.');
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Register the middleware alias**

`bootstrap/app.php`, dentro de `->withMiddleware(function (Middleware $middleware) { ... })`:

```php
$middleware->alias([
    'role' => \App\Http\Middleware\EnsureUserHasRole::class,
]);
```

- [ ] **Step 5: Add the route groups**

Adicionar a `routes/api.php`:

```php
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/ping', fn () => response()->json(['ok' => true]));
});

Route::middleware(['auth:sanctum', 'role:tecnico'])->prefix('tecnico')->group(function () {
    Route::get('/ping', fn () => response()->json(['ok' => true]));
});

Route::middleware(['auth:sanctum', 'role:cliente'])->prefix('cliente')->group(function () {
    Route::get('/ping', fn () => response()->json(['ok' => true]));
});
```

- [ ] **Step 6: Run tests**

```bash
php artisan test --filter=RoleMiddlewareTest
```

Expected: PASS (4 tests).

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: role-based route middleware and per-role route groups"
```

---

### Task 8: Resend mail transport

**Files:**
- Modify: `.env`, `.env.example`, `config/mail.php`
- Modify: `composer.json` dependency `resend/resend-laravel`
- Test: `tests/Feature/Mail/ResendConfigTest.php`

- [ ] **Step 1: Install the Resend Laravel package**

```bash
composer require resend/resend-laravel
php artisan vendor:publish --tag="resend-config"
```

Expected: `config/services.php` ganha chave `resend`, ou novo `config/resend.php` é publicado (confirmar em `config/` depois de correr).

- [ ] **Step 2: Configure env**

`.env` (valores reais) e `.env.example` (placeholders):

```
MAIL_MAILER=resend
RESEND_API_KEY=re_xxxxxxxxxxxxxxxxxxxxxxxx
MAIL_FROM_ADDRESS=noreply@oruidoscomputadores.pt
MAIL_FROM_NAME="O Rui dos Computadores"
```

- [ ] **Step 3: Write the failing test**

```php
<?php
// tests/Feature/Mail/ResendConfigTest.php

test('mail is configured to use resend in production-like env', function () {
    config(['mail.default' => 'resend']);

    expect(config('mail.default'))->toBe('resend');
    expect(config('mail.from.address'))->not->toBeEmpty();
});
```

- [ ] **Step 4: Run test**

```bash
php artisan test --filter=ResendConfigTest
```

Expected: PASS — teste só valida a configuração, sem chamadas de rede.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: configure Resend as the mail transport"
```

---

### Task 9: Invite flow — admin creates cliente, sends invite

**Files:**
- Create: `app/Mail/ConviteCliente.php`
- Create: `resources/views/emails/convite-cliente.blade.php`
- Create: `app/Http/Controllers/Admin/ClienteController.php`
- Modify: `routes/api.php`, `config/services.php`, `.env`, `.env.example`
- Test: `tests/Feature/Admin/CreateClienteTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Admin/CreateClienteTest.php

use App\Enums\UserRole;
use App\Mail\ConviteCliente;
use App\Models\Cliente;
use App\Models\Convite;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('admin creates a cliente and an invite email is sent', function () {
    Mail::fake();
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)->postJson('/api/admin/clientes', [
        'nome' => 'Ana Silva',
        'telefone' => '912345678',
        'email' => 'ana@example.com',
    ]);

    $response->assertCreated();
    $cliente = Cliente::firstWhere('email', 'ana@example.com');
    expect($cliente)->not->toBeNull();
    expect(Convite::where('cliente_id', $cliente->id)->exists())->toBeTrue();
    Mail::assertSent(ConviteCliente::class);
});

test('tecnico cannot create clientes', function () {
    $tecnico = User::factory()->create(['role' => UserRole::Tecnico]);

    $this->actingAs($tecnico)->postJson('/api/admin/clientes', ['nome' => 'Ana Silva'])
        ->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=CreateClienteTest
```

Expected: FAIL — rota `/api/admin/clientes` não existe.

- [ ] **Step 3: Add config for the public frontend URL**

`config/services.php`, adicionar:

```php
'frontend_url' => env('FRONTEND_PUBLIC_URL'),
```

`.env` e `.env.example`:

```
FRONTEND_PUBLIC_URL=https://oruidoscomputadores.pt
```

- [ ] **Step 4: Create the Mailable**

```php
<?php
// app/Mail/ConviteCliente.php

namespace App\Mail;

use App\Models\Convite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ConviteCliente extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Convite $convite,
        public string $plaintextToken,
    ) {}

    public function build()
    {
        $url = rtrim(config('services.frontend_url'), '/')."/ativar-conta/{$this->plaintextToken}";

        return $this->subject('Ativa a tua conta — O Rui dos Computadores')
            ->view('emails.convite-cliente', ['url' => $url, 'cliente' => $this->convite->cliente]);
    }
}
```

- [ ] **Step 5: Create the email view**

```blade
{{-- resources/views/emails/convite-cliente.blade.php --}}
<p>Olá {{ $cliente->nome }},</p>

<p>Foi criada uma ficha para ti no sistema d'O Rui dos Computadores. Para veres o estado das tuas intervenções e definires a tua password, clica no link abaixo:</p>

<p><a href="{{ $url }}">{{ $url }}</a></p>

<p>Este link expira em 7 dias.</p>
```

- [ ] **Step 6: Create the controller**

```php
<?php
// app/Http/Controllers/Admin/ClienteController.php

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

- [ ] **Step 7: Register the route**

`routes/api.php`, dentro do grupo `role:admin` do Task 7:

```php
Route::post('/clientes', [\App\Http\Controllers\Admin\ClienteController::class, 'store']);
```

- [ ] **Step 8: Run tests**

```bash
php artisan test --filter=CreateClienteTest
```

Expected: PASS (2 tests).

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: admin creates cliente and triggers invite email"
```

---

### Task 10: Invite activation — cliente fills own data + sets password

**Files:**
- Create: `app/Http/Controllers/Public/ConviteController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/ConviteActivationTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/ConviteActivationTest.php

use App\Enums\UserRole;
use App\Models\Cliente;
use App\Models\Convite;
use App\Models\User;
use Illuminate\Support\Str;

test('cliente activates account with a valid token', function () {
    $cliente = Cliente::create(['nome' => 'Ana Silva']);
    $plaintextToken = Str::random(64);
    Convite::create([
        'cliente_id' => $cliente->id,
        'token_hash' => hash('sha256', $plaintextToken),
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->postJson("/api/convites/{$plaintextToken}/completar", [
        'email' => 'ana@example.com',
        'morada' => 'Rua Exemplo, 1, Cascais',
        'nif' => '123456789',
        'password' => 'password-segura-123',
    ]);

    $response->assertOk();
    $cliente->refresh();
    expect($cliente->email)->toBe('ana@example.com');
    expect($cliente->user_id)->not->toBeNull();
    expect($cliente->user->role)->toBe(UserRole::Cliente);
    $this->assertAuthenticatedAs($cliente->user);
});

test('expired token is rejected', function () {
    $cliente = Cliente::create(['nome' => 'Ana Silva']);
    $plaintextToken = Str::random(64);
    Convite::create([
        'cliente_id' => $cliente->id,
        'token_hash' => hash('sha256', $plaintextToken),
        'expires_at' => now()->subDay(),
    ]);

    $this->postJson("/api/convites/{$plaintextToken}/completar", [
        'email' => 'ana@example.com',
        'password' => 'password-segura-123',
    ])->assertStatus(410);
});

test('already used token is rejected', function () {
    $cliente = Cliente::create(['nome' => 'Ana Silva']);
    $plaintextToken = Str::random(64);
    Convite::create([
        'cliente_id' => $cliente->id,
        'token_hash' => hash('sha256', $plaintextToken),
        'expires_at' => now()->addDays(7),
        'used_at' => now(),
    ]);

    $this->postJson("/api/convites/{$plaintextToken}/completar", [
        'email' => 'ana@example.com',
        'password' => 'password-segura-123',
    ])->assertStatus(410);
});

test('unknown token returns 404', function () {
    $this->postJson('/api/convites/token-que-nao-existe/completar', [
        'email' => 'ana@example.com',
        'password' => 'password-segura-123',
    ])->assertNotFound();
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=ConviteActivationTest
```

Expected: FAIL — rota não existe.

- [ ] **Step 3: Create the controller**

```php
<?php
// app/Http/Controllers/Public/ConviteController.php

namespace App\Http\Controllers\Public;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Convite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ConviteController extends Controller
{
    public function completar(Request $request, string $token)
    {
        $convite = Convite::where('token_hash', hash('sha256', $token))->first();

        abort_if(! $convite, 404, 'Convite não encontrado.');
        abort_if($convite->isExpired() || $convite->isUsed(), 410, 'Convite expirado ou já utilizado.');

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'morada' => ['nullable', 'string', 'max:255'],
            'nif' => ['nullable', 'digits:9'],
            'password' => ['required', 'string', 'min:10'],
        ]);

        $user = DB::transaction(function () use ($convite, $data) {
            $user = User::create([
                'name' => $convite->cliente->nome,
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => UserRole::Cliente,
            ]);

            $convite->cliente->update([
                'user_id' => $user->id,
                'email' => $data['email'],
                'morada' => $data['morada'] ?? $convite->cliente->morada,
                'nif' => $data['nif'] ?? $convite->cliente->nif,
            ]);

            $convite->update(['used_at' => now()]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['user' => ['id' => $user->id, 'name' => $user->name, 'role' => $user->role->value]]);
    }
}
```

- [ ] **Step 4: Register the route**

`routes/api.php`, fora de qualquer middleware auth (este endpoint autentica *pelo token*, o utilizador ainda não tem sessão):

```php
Route::post('/convites/{token}/completar', [\App\Http\Controllers\Public\ConviteController::class, 'completar']);
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --filter=ConviteActivationTest
```

Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: cliente completes account activation from invite link"
```

---

### Task 11: cPanel deploy config

**Files:**
- Create: `.cpanel.yml`
- Modify: `.env.example` (confirmar chaves), `README.md`

- [ ] **Step 1: Write `.cpanel.yml`**

Corre a cada pull disparado pelo cPanel Git Version Control. Copia a app do repo pulled para a docroot pública, sem tocar em `.env` nem `storage/` (têm que persistir entre deploys).

```yaml
---
deployment:
  tasks:
    - export DEPLOYPATH=/home/<cpanel-user>/api.oruidoscomputadores.pt/
    - rsync -av --exclude='.env' --exclude='.git' --exclude='storage/app' --exclude='storage/logs' ./ $DEPLOYPATH
    - /usr/local/bin/php -r "if (!file_exists('{$DEPLOYPATH}.env')) { echo 'AVISO: .env em falta em produção, criar manualmente.'; }"
```

Substituir `<cpanel-user>` pelo username real da conta cPanel assim que soubermos — é o único placeholder que fica por editar antes do primeiro deploy, documentado aqui de propósito.

- [ ] **Step 2: Confirm `.env.example` has every key introduced by this plan**

```bash
grep -E "SESSION_DOMAIN|SANCTUM_STATEFUL_DOMAINS|FRONTEND_URL|FRONTEND_PUBLIC_URL|MAIL_MAILER|RESEND_API_KEY|MAIL_FROM_ADDRESS" .env.example
```

Expected: as 7 chaves presentes (valores placeholder, nunca segredos reais).

- [ ] **Step 3: Add a deploy README section**

Adicionar a `README.md` (criar com base no default do Laravel se ainda não existir):

```markdown
## Deploy (cPanel, sem SSH)

1. cPanel → Git Version Control → clonar este repo em `/home/<user>/repositories/rui-tech-helper-api`.
2. Primeiro deploy: copiar manualmente `.env` (com os valores reais) para `/home/<user>/api.oruidoscomputadores.pt/.env` via File Manager ou FTP. Não está no git.
3. Cada `git push` para `main` + "Update from Remote" no cPanel corre `.cpanel.yml`, que sincroniza o código para a docroot sem tocar em `.env` nem `storage/`.
4. Migrations: correm sempre localmente, nunca no servidor — `php artisan migrate` contra o MySQL remoto (IP branco-listado em cPanel → Remote MySQL).
```

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "chore: add cPanel deploy config and deploy docs"
```

---

### Task 12: Full suite run + spec cross-check

**Files:** none created — verification task.

- [ ] **Step 1: Run the entire test suite**

```bash
php artisan test
```

Expected: todos os testes das Tasks 3–10 passam (18 testes: 2+2+2+4+4+2+4). Sem falhas, sem skips.

- [ ] **Step 2: Cross-check against the spec**

Reler `docs/superpowers/specs/2026-08-19-fundacao-backend-design.md` secção a secção, confirmar cobertura:
- Modelo de dados → Tasks 3, 4, 5.
- Fluxos de autenticação (admin/técnico) → Task 6.
- Fluxos de autenticação (cliente) → Tasks 9, 10.
- Roles/middleware → Task 7.
- Deploy → Task 11.
- Erros e testes → coberto inline em cada task (422/401/403/404/410 testados acima).

- [ ] **Step 3: Confirm commit trail**

```bash
git log --oneline -15
```

Confirmar 11 commits feat/chore desde o bootstrap. Última task do plano.

---

## Out of scope (next plans)

- CRM core (intervenções/tickets) — sub-projecto 2.
- IfthenPay/Moloni — sub-projecto 3 (MCP `ifthenpay` já instalado e a ligar; faltam chaves reais do contrato do Rui).
- Conteúdo do site editável — sub-projecto 4.
- Conversão do frontend público para SPA estático + ligação a esta API — sub-projecto 5. Até lá esta API não tem consumidor em produção; Tasks 1–12 verificam-se inteiramente via test suite + `php artisan serve` + curl/Postman.
