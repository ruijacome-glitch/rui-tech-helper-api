<?php

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
