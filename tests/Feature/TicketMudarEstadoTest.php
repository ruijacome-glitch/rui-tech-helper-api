<?php

use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Enums\UserRole;
use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\User;

function criarTicket(): Ticket
{
    $clienteUser = User::factory()->create(['role' => UserRole::Cliente]);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Teste',
        'email' => $clienteUser->email,
        'telefone' => '912345678',
    ]);

    return Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Computador nao arranca desde ontem.',
    ]);
}

test('mudarEstado actualiza estado do ticket e grava evento', function () {
    $ticket = criarTicket();
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $evento = $ticket->mudarEstado($admin, TicketEstado::EmAnalise, 'A analisar', true);

    expect($ticket->fresh()->estado)->toBe(TicketEstado::EmAnalise);
    expect($evento->estado_anterior)->toBe(TicketEstado::Aberto);
    expect($evento->estado_novo)->toBe(TicketEstado::EmAnalise);
    expect($evento->observacao)->toBe('A analisar');
    expect($evento->observacao_visivel_cliente)->toBeTrue();
});

test('mudarEstado observacao_visivel_cliente default false', function () {
    $ticket = criarTicket();
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $evento = $ticket->mudarEstado($admin, TicketEstado::EmCurso);

    expect($evento->observacao_visivel_cliente)->toBeFalse();
    expect($evento->observacao)->toBeNull();
});

test('varios eventos ficam ligados ao ticket', function () {
    $ticket = criarTicket();
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $ticket->mudarEstado($admin, TicketEstado::EmAnalise);
    $ticket->mudarEstado($admin, TicketEstado::EmCurso);

    expect($ticket->eventos()->count())->toBe(2);
});
