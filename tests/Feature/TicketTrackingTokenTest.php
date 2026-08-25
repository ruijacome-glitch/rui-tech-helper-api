<?php

use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\User;

test('ticket recebe tracking_token uuid automaticamente ao criar', function () {
    $clienteUser = User::factory()->create(['role' => 'cliente']);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Tracking',
        'email' => $clienteUser->email,
        'telefone' => '912345678',
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

    expect($ticket->tracking_token)->not->toBeNull();
    expect(\Illuminate\Support\Str::isUuid($ticket->tracking_token))->toBeTrue();
});

test('dois tickets tem tracking_tokens diferentes', function () {
    $clienteUser = User::factory()->create(['role' => 'cliente']);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Tracking 2',
        'email' => $clienteUser->email,
        'telefone' => '912345678',
    ]);

    $dados = [
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Nao arranca.',
    ];

    $ticket1 = Ticket::create($dados);
    $ticket2 = Ticket::create($dados);

    expect($ticket1->tracking_token)->not->toBe($ticket2->tracking_token);
});
