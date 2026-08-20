<?php

use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Enums\UserRole;
use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\User;

test('cria ticket com defaults e casts de enum', function () {
    $clienteUser = User::factory()->create(['role' => UserRole::Cliente]);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Teste',
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
        'descricao' => 'Computador nao arranca desde ontem.',
    ]);

    expect($ticket->estado)->toBe(TicketEstado::Aberto);
    expect($ticket->categoria)->toBe(TicketCategoria::Hardware);
    expect($ticket->cliente->id)->toBe($cliente->id);
    expect($ticket->tecnico_id)->toBeNull();
});

test('ticket pode ter tecnico atribuido', function () {
    $clienteUser = User::factory()->create(['role' => UserRole::Cliente]);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Teste',
        'email' => $clienteUser->email,
        'telefone' => '912345678',
    ]);
    $tecnico = User::factory()->create(['role' => UserRole::Tecnico]);

    $ticket = Ticket::create([
        'cliente_id' => $cliente->id,
        'tecnico_id' => $tecnico->id,
        'categoria' => TicketCategoria::Software,
        'prioridade' => TicketPrioridade::Urgente,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'Virus',
        'descricao' => 'Antivirus a alertar constantemente.',
    ]);

    expect($ticket->tecnico->id)->toBe($tecnico->id);
});
