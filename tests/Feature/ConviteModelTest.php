<?php

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
