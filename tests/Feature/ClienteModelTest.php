<?php

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
