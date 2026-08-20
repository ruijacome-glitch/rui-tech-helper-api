<?php

use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Enums\UserRole;
use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\TicketAnexo;
use App\Models\User;

test('cria anexo ligado a ticket e user', function () {
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
        'descricao' => 'Computador nao arranca.',
    ]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $anexo = TicketAnexo::create([
        'ticket_id' => $ticket->id,
        'user_id' => $admin->id,
        'path' => 'anexos/1/foto.jpg',
        'nome_original' => 'foto.jpg',
        'content_type' => 'image/jpeg',
        'size' => 12345,
    ]);

    expect($anexo->ticket->id)->toBe($ticket->id);
    expect($anexo->user->id)->toBe($admin->id);
});
