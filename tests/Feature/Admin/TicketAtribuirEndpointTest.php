<?php
// tests/Feature/Admin/TicketAtribuirEndpointTest.php

use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\User;

function criarTicketSemTecnico(): Ticket
{
    $clienteUser = User::factory()->create(['role' => 'cliente']);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Atribuir',
        'email' => 'cliente-atribuir@example.com',
        'telefone' => '912222222',
    ]);

    return Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'Sem tecnico',
        'descricao' => 'Aguarda atribuicao.',
    ]);
}

test('admin atribui tecnico a ticket', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketSemTecnico();

    $response = $this->actingAs($admin)->patchJson("/api/admin/tickets/{$ticket->id}/atribuir", [
        'tecnico_id' => $tecnico->id,
    ]);

    $response->assertOk();
    $response->assertJsonPath('ticket.tecnico_id', $tecnico->id);
    expect($ticket->fresh()->tecnico_id)->toBe($tecnico->id);
});

test('atribuir a user que nao e tecnico devolve 422', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $cliente = User::factory()->create(['role' => 'cliente']);
    $ticket = criarTicketSemTecnico();

    $response = $this->actingAs($admin)->patchJson("/api/admin/tickets/{$ticket->id}/atribuir", [
        'tecnico_id' => $cliente->id,
    ]);

    $response->assertStatus(422);
});

test('tecnico nao pode aceder ao endpoint de atribuicao', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketSemTecnico();

    $response = $this->actingAs($tecnico)->patchJson("/api/tecnico/tickets/{$ticket->id}/atribuir", [
        'tecnico_id' => $tecnico->id,
    ]);

    $response->assertStatus(404);
});
