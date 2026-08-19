<?php

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
