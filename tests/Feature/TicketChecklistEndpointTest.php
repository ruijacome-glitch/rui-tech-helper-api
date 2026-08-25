<?php

use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\User;

function criarTicketComTecnicoParaChecklist(?User $tecnico = null): Ticket
{
    $clienteUser = User::factory()->create(['role' => 'cliente']);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Checklist Endpoint',
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

test('admin marca item de checklist como concluido', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = criarTicketComTecnicoParaChecklist();

    $response = $this->actingAs($admin)->patchJson("/api/admin/tickets/{$ticket->id}/checklist/testar-fonte-alimentacao");

    $response->assertStatus(200);
    $response->assertJsonPath('resposta.item_chave', 'testar-fonte-alimentacao');
    $response->assertJsonPath('resposta.concluido', true);
    $response->assertJsonPath('resposta.concluido_por_user_id', $admin->id);
});

test('tecnico atribuido marca item de checklist', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketComTecnicoParaChecklist($tecnico);

    $response = $this->actingAs($tecnico)->patchJson("/api/tecnico/tickets/{$ticket->id}/checklist/verificar-ram");

    $response->assertStatus(200);
    $response->assertJsonPath('resposta.concluido', true);
});

test('segundo patch ao mesmo item devolve 409', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = criarTicketComTecnicoParaChecklist();

    $this->actingAs($admin)->patchJson("/api/admin/tickets/{$ticket->id}/checklist/testar-disco")
        ->assertStatus(200);

    $response = $this->actingAs($admin)->patchJson("/api/admin/tickets/{$ticket->id}/checklist/testar-disco");

    $response->assertStatus(409);
});

test('tecnico nao atribuido nao pode marcar checklist', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $outroTecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketComTecnicoParaChecklist($outroTecnico);

    $response = $this->actingAs($tecnico)->patchJson("/api/tecnico/tickets/{$ticket->id}/checklist/verificar-temperatura");

    $response->assertStatus(403);
});
