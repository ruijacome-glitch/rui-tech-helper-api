<?php

use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\User;

test('cliente ve timeline do seu ticket com observacoes visiveis apenas', function () {
    $clienteUser = User::factory()->create(['role' => 'cliente']);
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
        'descricao' => 'Nao arranca.',
    ]);
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket->mudarEstado($admin, TicketEstado::EmAnalise, 'Nota interna', false);
    $ticket->mudarEstado($admin, TicketEstado::EmCurso, 'Nota visivel', true);

    $response = $this->actingAs($clienteUser)->getJson("/api/cliente/tickets/{$ticket->id}");

    $response->assertStatus(200);
    $response->assertJsonCount(2, 'ticket.eventos');
    $response->assertJsonPath('ticket.eventos.0.observacao', null);
    $response->assertJsonPath('ticket.eventos.1.observacao', 'Nota visivel');
});

test('cliente nao pode ver ticket de outro cliente', function () {
    $clienteUser = User::factory()->create(['role' => 'cliente']);
    Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Teste',
        'email' => $clienteUser->email,
        'telefone' => '912345678',
    ]);
    $outroClienteUser = User::factory()->create(['role' => 'cliente']);
    $outroCliente = Cliente::create([
        'user_id' => $outroClienteUser->id,
        'nome' => 'Outro Cliente',
        'email' => $outroClienteUser->email,
        'telefone' => '913456789',
    ]);
    $ticket = Ticket::create([
        'cliente_id' => $outroCliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Nao arranca.',
    ]);

    $response = $this->actingAs($clienteUser)->getJson("/api/cliente/tickets/{$ticket->id}");

    $response->assertStatus(403);
});
