<?php

use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Orcamento;
use App\Models\Ticket;
use App\Models\TicketAnexo;
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

test('cliente ve anexos do seu ticket', function () {
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
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    TicketAnexo::create([
        'ticket_id' => $ticket->id,
        'user_id' => $tecnico->id,
        'path' => 'anexos/foto.jpg',
        'nome_original' => 'foto.jpg',
        'content_type' => 'image/jpeg',
        'size' => 12345,
    ]);

    $response = $this->actingAs($clienteUser)->getJson("/api/cliente/tickets/{$ticket->id}");

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'ticket.anexos');
    $response->assertJsonPath('ticket.anexos.0.nome_original', 'foto.jpg');
    $response->assertJsonMissingPath('ticket.anexos.0.path');
});

test('cliente ve itens e total do orcamento do seu ticket', function () {
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
    $orcamento = Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => 'pendente']);
    $orcamento->itens()->create(['descricao' => 'Fonte', 'quantidade' => 2, 'preco_unitario' => 45.50]);
    $orcamento->itens()->create(['descricao' => 'Cabo', 'quantidade' => 1, 'preco_unitario' => 9.00]);

    $response = $this->actingAs($clienteUser)->getJson("/api/cliente/tickets/{$ticket->id}");

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'ticket.orcamentos');
    $response->assertJsonCount(2, 'ticket.orcamentos.0.itens');
    $response->assertJsonPath('ticket.orcamentos.0.itens.0.descricao', 'Fonte');
    $response->assertJsonPath('ticket.orcamentos.0.total', 100);
});
