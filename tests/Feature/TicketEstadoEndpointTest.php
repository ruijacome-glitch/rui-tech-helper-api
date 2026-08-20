<?php

use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\User;

function criarTicketComTecnico(?User $tecnico = null): Ticket
{
    $clienteUser = User::factory()->create(['role' => 'cliente']);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Teste',
        'email' => $clienteUser->email,
        'telefone' => '912345678',
    ]);

    return Ticket::create([
        'cliente_id' => $cliente->id,
        'tecnico_id' => $tecnico?->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Nao arranca.',
    ]);
}

test('admin muda estado de qualquer ticket', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = criarTicketComTecnico();

    $response = $this->actingAs($admin)->patchJson("/api/admin/tickets/{$ticket->id}/estado", [
        'estado' => 'em_analise',
        'observacao' => 'A analisar',
        'observacao_visivel_cliente' => true,
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('ticket.estado', 'em_analise');
});

test('tecnico atribuido muda estado do seu ticket', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketComTecnico($tecnico);

    $response = $this->actingAs($tecnico)->patchJson("/api/tecnico/tickets/{$ticket->id}/estado", [
        'estado' => 'em_curso',
    ]);

    $response->assertStatus(200);
});

test('tecnico nao atribuido nao pode mudar estado', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $outroTecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketComTecnico($outroTecnico);

    $response = $this->actingAs($tecnico)->patchJson("/api/tecnico/tickets/{$ticket->id}/estado", [
        'estado' => 'em_curso',
    ]);

    $response->assertStatus(403);
});

test('validacao falha com estado invalido', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = criarTicketComTecnico();

    $response = $this->actingAs($admin)->patchJson("/api/admin/tickets/{$ticket->id}/estado", [
        'estado' => 'invalido',
    ]);

    $response->assertStatus(422);
});
