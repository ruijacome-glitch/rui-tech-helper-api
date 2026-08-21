# Conteúdo Site Configurável (fase 1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Backoffice-editable content (contacto, testemunho, preços) for the `rui-tech-helper` public site, replacing hardcoded values in `src/data/site.ts`, with the static site fetching from a new public API endpoint and falling back to its existing static values if the fetch fails.

**Architecture:** `rui-tech-helper-api` (Laravel) gains a `conteudos` key-value table (JSON) for the two singleton blocks, a `precos` table for the price line items, one public GET endpoint aggregating everything, and a Blade admin page (`/admin/conteudo`) behind the existing `role:admin` middleware for editing. `rui-tech-helper` (static site) fetches this endpoint client-side on mount and falls back to its current `src/data/site.ts` constants on any failure.

**Tech Stack:** Laravel 12, Pest (`php artisan test`), MySQL, Blade + plain CSS (no build step for admin views), vanilla `fetch` in the TanStack Start site (no new frontend dependency).

Spec: `docs/superpowers/specs/2026-08-21-conteudo-configuravel-design.md`.

---

## File Structure

**rui-tech-helper-api (new):**
- `database/migrations/2026_08_21_100000_create_conteudos_table.php`
- `database/migrations/2026_08_21_100100_create_precos_table.php`
- `app/Enums/PrecoSecao.php` — enum `Home`/`Precario`
- `app/Models/Conteudo.php` — key-value model, `chave` as primary key
- `app/Models/Preco.php` — price line model
- `database/seeders/ConteudoConfiguravelSeeder.php` — seeds both tables from the current `site.ts` values
- `app/Http/Controllers/Public/ConteudoSiteController.php` — `index()`, public aggregated GET
- `app/Http/Controllers/Admin/AdminAuthController.php` — Blade login (`create`/`store`/`destroy`), separate from the JSON `Auth\SessionController` used by the SPA/CRM
- `app/Http/Controllers/Admin/ConteudoAdminController.php` — `edit()`/`update()` for the admin form
- `resources/views/admin/layout.blade.php` — dark sidebar shell shared by admin pages
- `resources/views/admin/login.blade.php`
- `resources/views/admin/conteudo/edit.blade.php`
- `public/css/admin.css` — dark theme styles (sidebar, cards) shared by all admin Blade views
- `routes/web.php` — modified, adds admin login + `/admin/conteudo` routes
- `routes/api.php` — modified, adds public `GET /api/public/conteudo-site`
- `tests/Feature/ConteudoSiteEndpointTest.php`
- `tests/Feature/Admin/ConteudoAdminTest.php`

**rui-tech-helper (modified):**
- `src/data/site.ts` — no removals; values stay as fallback defaults
- `src/lib/conteudoSite.ts` (new) — fetch wrapper with timeout + fallback merge
- Pages that render contacto/preços/testemunho — wired to call the new fetch wrapper (exact files located in Task 9)

---

## Task 1: `conteudos` and `precos` migrations

**Files:**
- Create: `database/migrations/2026_08_21_100000_create_conteudos_table.php`
- Create: `database/migrations/2026_08_21_100100_create_precos_table.php`

- [ ] **Step 1: Write the `conteudos` migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conteudos', function (Blueprint $table) {
            $table->string('chave')->primary();
            $table->json('valor');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conteudos');
    }
};
```

- [ ] **Step 2: Write the `precos` migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('precos', function (Blueprint $table) {
            $table->id();
            $table->string('secao');
            $table->string('servico');
            $table->string('valor');
            $table->text('nota')->nullable();
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('precos');
    }
};
```

- [ ] **Step 3: Run migrations locally**

Run: `php artisan migrate`
Expected: `2026_08_21_100000_create_conteudos_table ... DONE` and `2026_08_21_100100_create_precos_table ... DONE`

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_08_21_100000_create_conteudos_table.php database/migrations/2026_08_21_100100_create_precos_table.php
git commit -m "feat: add conteudos and precos migrations"
```

---

## Task 2: `PrecoSecao` enum + `Conteudo`/`Preco` models

**Files:**
- Create: `app/Enums/PrecoSecao.php`
- Create: `app/Models/Conteudo.php`
- Create: `app/Models/Preco.php`
- Test: `tests/Feature/ConteudoModelTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/ConteudoModelTest.php
use App\Enums\PrecoSecao;
use App\Models\Conteudo;
use App\Models\Preco;

test('conteudo stores and casts json valor', function () {
    $conteudo = Conteudo::create([
        'chave' => 'contacto',
        'valor' => ['telefone' => '+351 91 155 69 01', 'email' => 'ola@oruidoscomputadores.pt', 'whatsapp' => 'https://wa.me/351911556901'],
    ]);

    $fresh = Conteudo::find('contacto');

    expect($fresh->valor)->toBe($conteudo->valor);
    expect($fresh->valor['email'])->toBe('ola@oruidoscomputadores.pt');
});

test('preco stores secao as enum', function () {
    $preco = Preco::create([
        'secao' => PrecoSecao::Home,
        'servico' => 'Diagnóstico',
        'valor' => 'Valor a confirmar',
        'nota' => 'Avaliação do problema.',
        'ordem' => 1,
    ]);

    expect(Preco::find($preco->id)->secao)->toBe(PrecoSecao::Home);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ConteudoModelTest`
Expected: FAIL — `Class "App\Models\Conteudo" not found` (or similar)

- [ ] **Step 3: Write the enum**

```php
<?php

namespace App\Enums;

enum PrecoSecao: string
{
    case Home = 'home';
    case Precario = 'precario';
}
```

- [ ] **Step 4: Write the `Conteudo` model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conteudo extends Model
{
    protected $primaryKey = 'chave';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['chave', 'valor'];

    protected function casts(): array
    {
        return [
            'valor' => 'array',
        ];
    }
}
```

- [ ] **Step 5: Write the `Preco` model**

```php
<?php

namespace App\Models;

use App\Enums\PrecoSecao;
use Illuminate\Database\Eloquent\Model;

class Preco extends Model
{
    protected $fillable = ['secao', 'servico', 'valor', 'nota', 'ordem'];

    protected function casts(): array
    {
        return [
            'secao' => PrecoSecao::class,
            'ordem' => 'integer',
        ];
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=ConteudoModelTest`
Expected: PASS, 2 tests

- [ ] **Step 7: Commit**

```bash
git add app/Enums/PrecoSecao.php app/Models/Conteudo.php app/Models/Preco.php tests/Feature/ConteudoModelTest.php
git commit -m "feat: add Conteudo/Preco models and PrecoSecao enum"
```

---

## Task 3: Seeder with current `site.ts` values

**Files:**
- Create: `database/seeders/ConteudoConfiguravelSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Write the seeder**

Values copied verbatim from `d:/Projectos/o Rui dos Computadores/assets/rui-tech-helper/src/data/site.ts` (`contacto`, `testemunhoExemplo`, `precos`, `precarioAreas`) as of 2026-08-21.

```php
<?php

namespace Database\Seeders;

use App\Enums\PrecoSecao;
use App\Models\Conteudo;
use App\Models\Preco;
use Illuminate\Database\Seeder;

class ConteudoConfiguravelSeeder extends Seeder
{
    public function run(): void
    {
        Conteudo::updateOrCreate(['chave' => 'contacto'], [
            'valor' => [
                'telefone' => '+351 91 155 69 01',
                'email' => 'ola@oruidoscomputadores.pt',
                'whatsapp' => 'https://wa.me/351911556901',
            ],
        ]);

        Conteudo::updateOrCreate(['chave' => 'testemunho'], [
            'valor' => [
                'citacao' => 'Finalmente alguém que explica sem complicar.',
                'atribuicao' => 'Exemplo editorial — a validar antes da publicação',
            ],
        ]);

        $precosHome = [
            ['servico' => 'Diagnóstico', 'valor' => 'Valor a confirmar', 'nota' => 'Avaliação do problema e explicação do que é preciso fazer.'],
            ['servico' => 'Assistência remota', 'valor' => 'Valor a confirmar', 'nota' => 'Resolução à distância, contigo a acompanhar.'],
            ['servico' => 'Assistência ao domicílio', 'valor' => 'Valor a confirmar', 'nota' => 'Deslocação em Cascais e arredores.'],
        ];

        foreach ($precosHome as $ordem => $item) {
            Preco::updateOrCreate(
                ['secao' => PrecoSecao::Home, 'servico' => $item['servico']],
                ['valor' => $item['valor'], 'nota' => $item['nota'], 'ordem' => $ordem]
            );
        }

        $precarioAreas = [
            ['servico' => 'Limpeza e optimização', 'valor' => 'Valor a confirmar', 'nota' => 'Limpeza física, arranque, espaço em disco e desempenho geral.'],
            ['servico' => 'Instalação e configuração', 'valor' => 'Valor a confirmar', 'nota' => 'Sistema, contas, email, cópias de segurança e equipamento novo.'],
            ['servico' => 'Redes e periféricos', 'valor' => 'Valor a confirmar', 'nota' => 'Wi-Fi, routers, repetidores, impressoras e equipamento partilhado.'],
            ['servico' => 'Recuperação de dados', 'valor' => 'Mediante avaliação', 'nota' => 'Depende sempre do estado do equipamento. Nunca há garantia de recuperação.'],
        ];

        foreach ($precarioAreas as $ordem => $item) {
            Preco::updateOrCreate(
                ['secao' => PrecoSecao::Precario, 'servico' => $item['servico']],
                ['valor' => $item['valor'], 'nota' => $item['nota'], 'ordem' => $ordem]
            );
        }
    }
}
```

- [ ] **Step 2: Wire it into `DatabaseSeeder`**

Modify `database/seeders/DatabaseSeeder.php`, inside `run()`, after the existing `User::factory()->create(...)` call:

```php
        $this->call(ConteudoConfiguravelSeeder::class);
```

- [ ] **Step 3: Run the seeder locally and verify rows**

Run: `php artisan db:seed --class=ConteudoConfiguravelSeeder`
Then: `php artisan tinker --execute="dump(App\Models\Conteudo::all()->count(), App\Models\Preco::all()->count());"`
Expected: `2` and `7`

- [ ] **Step 4: Commit**

```bash
git add database/seeders/ConteudoConfiguravelSeeder.php database/seeders/DatabaseSeeder.php
git commit -m "feat: seed conteudos/precos from current site.ts values"
```

---

## Task 4: Public aggregated endpoint

**Files:**
- Create: `app/Http/Controllers/Public/ConteudoSiteController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/ConteudoSiteEndpointTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/ConteudoSiteEndpointTest.php
use App\Enums\PrecoSecao;
use App\Models\Conteudo;
use App\Models\Preco;

test('public conteudo-site endpoint returns aggregated shape', function () {
    Conteudo::create(['chave' => 'contacto', 'valor' => ['telefone' => '911', 'email' => 'a@a.pt', 'whatsapp' => 'https://wa.me/1']]);
    Conteudo::create(['chave' => 'testemunho', 'valor' => ['citacao' => 'Óptimo', 'atribuicao' => 'Cliente']]);
    Preco::create(['secao' => PrecoSecao::Home, 'servico' => 'Diagnóstico', 'valor' => '20 €', 'nota' => null, 'ordem' => 0]);
    Preco::create(['secao' => PrecoSecao::Precario, 'servico' => 'Redes', 'valor' => '30 €', 'nota' => 'nota', 'ordem' => 0]);

    $response = $this->getJson('/api/public/conteudo-site');

    $response->assertOk()->assertJson([
        'contacto' => ['telefone' => '911', 'email' => 'a@a.pt', 'whatsapp' => 'https://wa.me/1'],
        'testemunho' => ['citacao' => 'Óptimo', 'atribuicao' => 'Cliente'],
        'precosHome' => [['servico' => 'Diagnóstico', 'valor' => '20 €', 'nota' => null]],
        'precarioAreas' => [['servico' => 'Redes', 'valor' => '30 €', 'nota' => 'nota']],
    ]);
});

test('public conteudo-site endpoint returns empty structure when tables are empty', function () {
    $response = $this->getJson('/api/public/conteudo-site');

    $response->assertOk()->assertJson([
        'contacto' => null,
        'testemunho' => null,
        'precosHome' => [],
        'precarioAreas' => [],
    ]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ConteudoSiteEndpointTest`
Expected: FAIL — 404 (route not defined)

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers\Public;

use App\Enums\PrecoSecao;
use App\Http\Controllers\Controller;
use App\Models\Conteudo;
use App\Models\Preco;

class ConteudoSiteController extends Controller
{
    public function index()
    {
        $contacto = Conteudo::find('contacto');
        $testemunho = Conteudo::find('testemunho');

        return response()->json([
            'contacto' => $contacto?->valor,
            'testemunho' => $testemunho?->valor,
            'precosHome' => $this->precosPorSecao(PrecoSecao::Home),
            'precarioAreas' => $this->precosPorSecao(PrecoSecao::Precario),
        ]);
    }

    private function precosPorSecao(PrecoSecao $secao): array
    {
        return Preco::where('secao', $secao)
            ->orderBy('ordem')
            ->get(['servico', 'valor', 'nota'])
            ->map(fn (Preco $preco) => [
                'servico' => $preco->servico,
                'valor' => $preco->valor,
                'nota' => $preco->nota,
            ])
            ->all();
    }
}
```

- [ ] **Step 4: Add the route**

Modify `routes/api.php`, after the existing `Route::post('/webhooks/ifthenpay', ...)` line and before `Route::middleware('auth:sanctum')->group(...)`:

```php
Route::get('/public/conteudo-site', [\App\Http\Controllers\Public\ConteudoSiteController::class, 'index']);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=ConteudoSiteEndpointTest`
Expected: PASS, 2 tests

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Public/ConteudoSiteController.php routes/api.php tests/Feature/ConteudoSiteEndpointTest.php
git commit -m "feat: add public GET /api/public/conteudo-site endpoint"
```

---

## Task 5: Admin dark-theme layout + shared CSS

**Files:**
- Create: `resources/views/admin/layout.blade.php`
- Create: `public/css/admin.css`

- [ ] **Step 1: Write the shared CSS**

```css
/* public/css/admin.css */
:root {
  --bg: #0f1115;
  --bg-card: #171a21;
  --border: #262a33;
  --text: #e6e8eb;
  --text-muted: #8b909c;
  --accent: #3b82f6;
  --accent-hover: #2563eb;
  --danger: #ef4444;
}

* { box-sizing: border-box; }

body {
  margin: 0;
  background: var(--bg);
  color: var(--text);
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  display: flex;
  min-height: 100vh;
}

.admin-sidebar {
  width: 220px;
  background: var(--bg-card);
  border-right: 1px solid var(--border);
  padding: 24px 16px;
  flex-shrink: 0;
}

.admin-sidebar .brand {
  font-weight: 600;
  font-size: 1.1rem;
  margin-bottom: 32px;
}

.admin-sidebar nav a {
  display: block;
  padding: 10px 12px;
  border-radius: 8px;
  color: var(--text-muted);
  text-decoration: none;
  margin-bottom: 4px;
}

.admin-sidebar nav a.active,
.admin-sidebar nav a:hover {
  background: var(--accent);
  color: #fff;
}

.admin-main {
  flex: 1;
  padding: 32px;
}

.admin-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 24px;
  margin-bottom: 24px;
}

.admin-card h2 {
  margin-top: 0;
  font-size: 1.1rem;
}

.admin-field { margin-bottom: 16px; }

.admin-field label {
  display: block;
  font-size: 0.85rem;
  color: var(--text-muted);
  margin-bottom: 6px;
}

.admin-field input,
.admin-field textarea {
  width: 100%;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 10px 12px;
  color: var(--text);
  font-size: 0.95rem;
}

.admin-field .error { color: var(--danger); font-size: 0.8rem; margin-top: 4px; }

.admin-btn {
  background: var(--accent);
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 10px 20px;
  font-size: 0.95rem;
  cursor: pointer;
}

.admin-btn:hover { background: var(--accent-hover); }

.admin-flash {
  background: #14301f;
  border: 1px solid #1f6b3b;
  color: #7ee2a3;
  padding: 12px 16px;
  border-radius: 8px;
  margin-bottom: 24px;
}

.admin-precos-table { width: 100%; border-collapse: collapse; }
.admin-precos-table th { text-align: left; color: var(--text-muted); font-size: 0.8rem; padding: 8px; }
.admin-precos-table td { padding: 8px; border-top: 1px solid var(--border); }
.admin-precos-table .servico-label { color: var(--text-muted); }
```

- [ ] **Step 2: Write the layout**

```blade
{{-- resources/views/admin/layout.blade.php --}}
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Admin') — O Rui dos Computadores</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
    @auth
        <aside class="admin-sidebar">
            <div class="brand">O Rui dos Computadores</div>
            <nav>
                <a href="{{ route('admin.conteudo.edit') }}" class="{{ request()->routeIs('admin.conteudo.*') ? 'active' : '' }}">Conteúdo</a>
            </nav>
        </aside>
    @endauth
    <main class="admin-main">
        @yield('content')
    </main>
</body>
</html>
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/admin/layout.blade.php public/css/admin.css
git commit -m "feat: add dark-theme admin layout and shared styles"
```

---

## Task 6: Admin login (Blade, `web` guard)

**Files:**
- Create: `app/Http/Controllers/Admin/AdminAuthController.php`
- Create: `resources/views/admin/login.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/AdminAuthTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Admin/AdminAuthTest.php
use App\Enums\UserRole;
use App\Models\User;

test('admin can log in via the blade form and reach the conteudo page', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin, 'password' => bcrypt('senha-forte')]);

    $response = $this->post('/admin/login', [
        'email' => $admin->email,
        'password' => 'senha-forte',
    ]);

    $response->assertRedirect('/admin/conteudo');
    $this->assertAuthenticatedAs($admin);
});

test('non-admin cannot reach the conteudo page', function () {
    $tecnico = User::factory()->create(['role' => UserRole::Tecnico]);

    $response = $this->actingAs($tecnico)->get('/admin/conteudo');

    $response->assertForbidden();
});

test('guest is redirected to login when visiting the conteudo page', function () {
    $response = $this->get('/admin/conteudo');

    $response->assertRedirect('/admin/login');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AdminAuthTest`
Expected: FAIL — 404 on `/admin/login`

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    public function create()
    {
        return view('admin.login');
    }

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

        return redirect('/admin/conteudo');
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/admin/login');
    }
}
```

- [ ] **Step 4: Write the login view**

```blade
{{-- resources/views/admin/login.blade.php --}}
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Login — Admin O Rui dos Computadores</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body style="align-items: center; justify-content: center;">
    <div class="admin-card" style="width: 320px;">
        <h2>Login admin</h2>
        @if ($errors->any())
            <div class="admin-field error">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('admin.login.store') }}">
            @csrf
            <div class="admin-field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required>
            </div>
            <div class="admin-field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button class="admin-btn" type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>
```

- [ ] **Step 5: Add routes**

Modify `routes/web.php` — replace the whole file with:

```php
<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\ConteudoAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin/login', [AdminAuthController::class, 'create'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'store'])->name('admin.login.store');
Route::post('/admin/logout', [AdminAuthController::class, 'destroy'])->name('admin.logout');

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/conteudo', [ConteudoAdminController::class, 'edit'])->name('conteudo.edit');
    Route::put('/conteudo', [ConteudoAdminController::class, 'update'])->name('conteudo.update');
});
```

Note: `ConteudoAdminController` doesn't exist yet — Task 7 creates it. This route registration is safe to add now since Laravel only resolves the controller class when the route is hit, and this task's tests hit `/admin/login`, not `/admin/conteudo`'s content (only its redirect/403 behavior, which fires from the `auth`/`role` middleware before the controller loads — this works even before Task 7). If `php artisan route:list` is run before Task 7, it will still list correctly since class resolution is lazy.

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=AdminAuthTest`
Expected: PASS, 3 tests

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/AdminAuthController.php resources/views/admin/login.blade.php routes/web.php tests/Feature/Admin/AdminAuthTest.php
git commit -m "feat: add Blade admin login (web guard, role:admin gated)"
```

---

## Task 7: Admin conteúdo edit page

**Files:**
- Create: `app/Http/Controllers/Admin/ConteudoAdminController.php`
- Create: `resources/views/admin/conteudo/edit.blade.php`
- Test: `tests/Feature/Admin/ConteudoAdminTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Admin/ConteudoAdminTest.php
use App\Enums\PrecoSecao;
use App\Enums\UserRole;
use App\Models\Conteudo;
use App\Models\Preco;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
    Conteudo::create(['chave' => 'contacto', 'valor' => ['telefone' => '911', 'email' => 'a@a.pt', 'whatsapp' => 'https://wa.me/1']]);
    Conteudo::create(['chave' => 'testemunho', 'valor' => ['citacao' => 'Óptimo', 'atribuicao' => 'Cliente']]);
    $this->preco = Preco::create(['secao' => PrecoSecao::Home, 'servico' => 'Diagnóstico', 'valor' => 'Valor a confirmar', 'nota' => null, 'ordem' => 0]);
});

test('admin sees current values on the edit page', function () {
    $response = $this->actingAs($this->admin)->get('/admin/conteudo');

    $response->assertOk()->assertSee('911')->assertSee('Óptimo')->assertSee('Diagnóstico');
});

test('admin can update contacto, testemunho and a preco value', function () {
    $response = $this->actingAs($this->admin)->put('/admin/conteudo', [
        'contacto' => ['telefone' => '912345678', 'email' => 'novo@a.pt', 'whatsapp' => 'https://wa.me/912345678'],
        'testemunho' => ['citacao' => 'Nova citação', 'atribuicao' => 'Cliente Real'],
        'precos' => [
            $this->preco->id => ['valor' => '25 €', 'nota' => 'Actualizado'],
        ],
    ]);

    $response->assertRedirect('/admin/conteudo');
    expect(Conteudo::find('contacto')->valor['telefone'])->toBe('912345678');
    expect(Conteudo::find('testemunho')->valor['citacao'])->toBe('Nova citação');
    expect(Preco::find($this->preco->id)->valor)->toBe('25 €');
});

test('update fails validation with invalid email', function () {
    $response = $this->actingAs($this->admin)->put('/admin/conteudo', [
        'contacto' => ['telefone' => '912345678', 'email' => 'nao-e-email', 'whatsapp' => 'https://wa.me/1'],
        'testemunho' => ['citacao' => 'X', 'atribuicao' => 'Y'],
        'precos' => [$this->preco->id => ['valor' => '25 €', 'nota' => null]],
    ]);

    $response->assertSessionHasErrors('contacto.email');
    expect(Conteudo::find('contacto')->valor['email'])->toBe('a@a.pt');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ConteudoAdminTest`
Expected: FAIL — `Target class [App\Http\Controllers\Admin\ConteudoAdminController] does not exist`

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conteudo;
use App\Models\Preco;
use Illuminate\Http\Request;

class ConteudoAdminController extends Controller
{
    public function edit()
    {
        return view('admin.conteudo.edit', [
            'contacto' => Conteudo::find('contacto')?->valor ?? ['telefone' => '', 'email' => '', 'whatsapp' => ''],
            'testemunho' => Conteudo::find('testemunho')?->valor ?? ['citacao' => '', 'atribuicao' => ''],
            'precos' => Preco::orderBy('secao')->orderBy('ordem')->get(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'contacto.telefone' => ['required', 'string', 'max:30'],
            'contacto.email' => ['required', 'email', 'max:255'],
            'contacto.whatsapp' => ['required', 'url', 'max:255'],
            'testemunho.citacao' => ['required', 'string', 'max:500'],
            'testemunho.atribuicao' => ['required', 'string', 'max:255'],
            'precos' => ['required', 'array'],
            'precos.*.valor' => ['required', 'string', 'max:100'],
            'precos.*.nota' => ['nullable', 'string', 'max:500'],
        ]);

        Conteudo::updateOrCreate(['chave' => 'contacto'], ['valor' => $data['contacto']]);
        Conteudo::updateOrCreate(['chave' => 'testemunho'], ['valor' => $data['testemunho']]);

        foreach ($data['precos'] as $id => $preco) {
            Preco::whereKey($id)->update(['valor' => $preco['valor'], 'nota' => $preco['nota'] ?? null]);
        }

        return redirect()->route('admin.conteudo.edit')->with('status', 'Conteúdo actualizado.');
    }
}
```

- [ ] **Step 4: Write the edit view**

```blade
{{-- resources/views/admin/conteudo/edit.blade.php --}}
@extends('admin.layout')

@section('title', 'Conteúdo')

@section('content')
    @if (session('status'))
        <div class="admin-flash">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.conteudo.update') }}">
        @csrf
        @method('PUT')

        <div class="admin-card">
            <h2>Contacto</h2>
            <div class="admin-field">
                <label for="telefone">Telefone</label>
                <input type="text" id="telefone" name="contacto[telefone]" value="{{ old('contacto.telefone', $contacto['telefone']) }}">
                @error('contacto.telefone') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="admin-field">
                <label for="email">Email</label>
                <input type="email" id="email" name="contacto[email]" value="{{ old('contacto.email', $contacto['email']) }}">
                @error('contacto.email') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="admin-field">
                <label for="whatsapp">WhatsApp (URL)</label>
                <input type="text" id="whatsapp" name="contacto[whatsapp]" value="{{ old('contacto.whatsapp', $contacto['whatsapp']) }}">
                @error('contacto.whatsapp') <div class="error">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="admin-card">
            <h2>Testemunho</h2>
            <div class="admin-field">
                <label for="citacao">Citação</label>
                <textarea id="citacao" name="testemunho[citacao]">{{ old('testemunho.citacao', $testemunho['citacao']) }}</textarea>
                @error('testemunho.citacao') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="admin-field">
                <label for="atribuicao">Atribuição</label>
                <input type="text" id="atribuicao" name="testemunho[atribuicao]" value="{{ old('testemunho.atribuicao', $testemunho['atribuicao']) }}">
                @error('testemunho.atribuicao') <div class="error">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="admin-card">
            <h2>Preços</h2>
            <table class="admin-precos-table">
                <thead>
                    <tr><th>Serviço</th><th>Valor</th><th>Nota</th></tr>
                </thead>
                <tbody>
                    @foreach ($precos as $preco)
                        <tr>
                            <td class="servico-label">{{ $preco->servico }} <small>({{ $preco->secao->value }})</small></td>
                            <td><input type="text" name="precos[{{ $preco->id }}][valor]" value="{{ old("precos.{$preco->id}.valor", $preco->valor) }}"></td>
                            <td><input type="text" name="precos[{{ $preco->id }}][nota]" value="{{ old("precos.{$preco->id}.nota", $preco->nota) }}"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <button class="admin-btn" type="submit">Guardar</button>
    </form>
@endsection
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=ConteudoAdminTest`
Expected: PASS, 3 tests

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/ConteudoAdminController.php resources/views/admin/conteudo/edit.blade.php tests/Feature/Admin/ConteudoAdminTest.php
git commit -m "feat: add admin conteudo edit page (contacto, testemunho, precos)"
```

---

## Task 8: Full test suite + deploy

**Files:** none new — verification task.

- [ ] **Step 1: Run the full local suite**

Run: `php artisan test`
Expected: all tests pass (existing 98 + new ones from Tasks 2, 4, 6, 7)

- [ ] **Step 2: Push to GitHub**

```bash
git push origin master
```

- [ ] **Step 3: Deploy via cPanel MCP**

Call `mcp__cpanel__update_git_repo` on `/home/mercadom/repositories/rui-tech-helper-api-src`, then `mcp__cpanel__deploy_git_repo` on the same path. See memory `rui-tech-helper-deploy` for the known-working pipeline.

- [ ] **Step 4: Run migrations + seeder against production DB**

Locally, with the production `.env` credentials active (per the established pattern — migrations always run from the dev machine against the IP-whitelisted remote MySQL, never on the server):

```bash
php artisan migrate --force
php artisan db:seed --class=ConteudoConfiguravelSeeder --force
```

- [ ] **Step 5: Verify the public endpoint in production**

```bash
curl -s https://api.oruidoscomputadores.pt/api/public/conteudo-site
```

Expected: JSON with `contacto`, `testemunho`, `precosHome` (3 items), `precarioAreas` (4 items).

- [ ] **Step 6: Verify the admin page in production**

Visit `https://api.oruidoscomputadores.pt/admin/login` in a browser, log in with an existing admin user, confirm `/admin/conteudo` renders with the dark sidebar/card style and shows the seeded values.

---

## Task 9: Wire the public site to fetch conteúdo, with fallback

**Files:**
- Create: `d:/Projectos/o Rui dos Computadores/assets/rui-tech-helper/src/lib/conteudoSite.ts`
- Modify: pages under `d:/Projectos/o Rui dos Computadores/assets/rui-tech-helper/src/routes/` (or equivalent) that currently import `contacto`, `precos`, `precarioAreas`, `testemunhoExemplo` from `src/data/site.ts` — locate them with `grep -rl "from '@/data/site'" src` (or the project's actual import alias) at execution time, since the exact route file names weren't enumerated during planning.

- [ ] **Step 1: Write the fetch wrapper**

```typescript
// src/lib/conteudoSite.ts
import { contacto as contactoFallback, precos as precosFallback, precarioAreas as precarioAreasFallback, testemunhoExemplo as testemunhoFallback } from "@/data/site";

export type ConteudoSite = {
  contacto: typeof contactoFallback;
  testemunho: typeof testemunhoFallback;
  precosHome: typeof precosFallback;
  precarioAreas: typeof precarioAreasFallback;
};

const API_URL = "https://api.oruidoscomputadores.pt/api/public/conteudo-site";
const TIMEOUT_MS = 3000;

export async function fetchConteudoSite(): Promise<ConteudoSite> {
  const fallback: ConteudoSite = {
    contacto: contactoFallback,
    testemunho: testemunhoFallback,
    precosHome: precosFallback,
    precarioAreas: precarioAreasFallback,
  };

  try {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), TIMEOUT_MS);
    const response = await fetch(API_URL, { signal: controller.signal });
    clearTimeout(timeout);

    if (!response.ok) return fallback;

    const data = await response.json();

    return {
      contacto: data.contacto ?? fallback.contacto,
      testemunho: data.testemunho ?? fallback.testemunho,
      precosHome: data.precosHome?.length ? data.precosHome : fallback.precosHome,
      precarioAreas: data.precarioAreas?.length ? data.precarioAreas : fallback.precarioAreas,
    };
  } catch {
    return fallback;
  }
}
```

- [ ] **Step 2: Locate the consuming pages**

Run: `grep -rl "from \"@/data/site\"\|from '@/data/site'" "d:/Projectos/o Rui dos Computadores/assets/rui-tech-helper/src"`

This lists every file importing `contacto`, `precos`, `precarioAreas`, or `testemunhoExemplo` today. For each file that renders one of these four values on a page the visitor sees (not layout/nav-only usages of `contacto`), replace the static import usage with a call to `fetchConteudoSite()` inside the component's existing data-loading mechanism (loader/`useEffect`, matching whatever pattern the file already uses for other async data — check a sibling route file if unsure), storing the result in local state initialized to the fallback values so first paint is never empty.

- [ ] **Step 3: Manual verification in browser**

Run: `npm run dev` in `rui-tech-helper`, open the home page and `/precario`.
Expected: values match what Task 8 seeded/updated in production (confirms the fetch path works end-to-end).

Then block the request (browser devtools → Network → block `api.oruidoscomputadores.pt`) and reload.
Expected: page still renders, showing the static fallback values from `site.ts`, no blank sections or console-visible crash.

- [ ] **Step 4: Commit**

```bash
git add src/lib/conteudoSite.ts
git add -u
git commit -m "feat: fetch contacto/precos/testemunho from API with static fallback"
```

---

## Self-Review Notes

- **Spec coverage:** migrations+models (spec "Modelo de dados") → Tasks 1-2; seed (spec "Seed inicial") → Task 3; public endpoint (spec "API pública") → Task 4; admin UI + auth + style (spec "Admin (Blade)") → Tasks 5-7; error handling / fallback (spec "Fluxo de dados no site público") → Task 9; testing (spec "Testes") → covered inline in Tasks 2/4/6/7 plus manual check in Task 9. Deploy steps folded into Task 8 per the established `rui-tech-helper-deploy` pipeline.
- **Placeholder scan:** no TBD/TODO; Task 9 Step 2 is intentionally a locate-and-repeat instruction (not a placeholder) because `rui-tech-helper`'s exact route file names weren't read during planning — the grep command given is exact and the pattern to apply is fully specified.
- **Type consistency:** `Conteudo::find('contacto')?->valor` used consistently across Task 4 (public controller) and Task 7 (admin controller); `Preco` fillable/cast fields (`secao`, `servico`, `valor`, `nota`, `ordem`) match across Tasks 2, 3, 4, 7.
