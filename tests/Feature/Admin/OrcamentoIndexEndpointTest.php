<?php
// tests/Feature/Admin/OrcamentoIndexEndpointTest.php

use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Orcamento;
use App\Models\Ticket;
use App\Models\User;

function criarTicketParaOrcamentoIndex(): Ticket
{
    $clienteUser = User::factory()->create(['role' => 'cliente']);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Orc',
        'email' => 'cliente-orc@example.com',
        'telefone' => '913333333',
    ]);

    return Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'Ticket com orcamento',
        'descricao' => 'Descricao.',
    ]);
}

test('admin lista orcamentos com itens e ticket', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = criarTicketParaOrcamentoIndex();
    $orcamento = Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => 'pendente']);
    $orcamento->itens()->create(['descricao' => 'Fonte', 'quantidade' => 1, 'preco_unitario' => 45.50]);

    $response = $this->actingAs($admin)->getJson('/api/admin/orcamentos');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.itens.0.descricao', 'Fonte');
    $response->assertJsonPath('data.0.ticket.id', $ticket->id);
});

test('admin filtra orcamentos por estado', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = criarTicketParaOrcamentoIndex();
    Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => 'pendente']);
    Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 2, 'estado' => 'aprovado']);

    $response = $this->actingAs($admin)->getJson('/api/admin/orcamentos?estado=aprovado');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.estado', 'aprovado');
});

test('tecnico nao pode listar orcamentos admin', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);

    $response = $this->actingAs($tecnico)->getJson('/api/admin/orcamentos');

    $response->assertStatus(403);
});
