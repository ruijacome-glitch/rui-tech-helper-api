<?php

use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Orcamento;
use App\Models\Ticket;
use App\Models\User;

function criarTicketCompleto(?User $tecnico = null): Ticket
{
    $clienteUser = User::factory()->create(['role' => 'cliente']);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Show',
        'email' => 'cliente-show@example.com',
        'telefone' => '911111111',
    ]);

    $ticket = Ticket::create([
        'cliente_id' => $cliente->id,
        'tecnico_id' => $tecnico?->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Nao arranca.',
    ]);

    $orcamento = Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => 'pendente']);
    $orcamento->itens()->create(['descricao' => 'Fonte', 'quantidade' => 1, 'preco_unitario' => 45.50]);

    return $ticket;
}

test('admin ve detalhe completo de qualquer ticket', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketCompleto($tecnico);

    $response = $this->actingAs($admin)->getJson("/api/admin/tickets/{$ticket->id}");

    $response->assertOk();
    $response->assertJsonPath('ticket.id', $ticket->id);
    $response->assertJsonPath('ticket.cliente.nome', 'Cliente Show');
    $response->assertJsonPath('ticket.tecnico.id', $tecnico->id);
    $response->assertJsonCount(1, 'ticket.orcamentos');
    $response->assertJsonPath('ticket.orcamentos.0.itens.0.descricao', 'Fonte');
});

test('tecnico atribuido ve detalhe do seu ticket', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketCompleto($tecnico);

    $response = $this->actingAs($tecnico)->getJson("/api/tecnico/tickets/{$ticket->id}");

    $response->assertOk();
    $response->assertJsonPath('ticket.id', $ticket->id);
});

test('tecnico nao atribuido recebe 403 no detalhe', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $outroTecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketCompleto($outroTecnico);

    $response = $this->actingAs($tecnico)->getJson("/api/tecnico/tickets/{$ticket->id}");

    $response->assertStatus(403);
});
