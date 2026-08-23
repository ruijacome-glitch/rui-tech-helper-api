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
