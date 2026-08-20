<?php
use App\Enums\UserRole;
use App\Models\Cliente;
use App\Models\User;

test('user tem relacao cliente quando ligado', function () {
    $user = User::factory()->create(['role' => UserRole::Cliente]);
    $cliente = Cliente::create([
        'user_id' => $user->id,
        'nome' => 'Cliente Teste',
        'email' => $user->email,
        'telefone' => '912345678',
    ]);

    expect($user->cliente)->not->toBeNull();
    expect($user->cliente->id)->toBe($cliente->id);
});

test('user->cliente e null quando nao ligado', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    expect($user->cliente)->toBeNull();
});
