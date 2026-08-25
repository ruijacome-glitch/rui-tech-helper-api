<?php

use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\User;

function criarTicketEmDiagnostico(?User $tecnico = null): Ticket
{
    $clienteUser = User::factory()->create(['role' => 'cliente']);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Gate Checklist',
        'email' => $clienteUser->email,
        'telefone' => '912345678',
    ]);

    return Ticket::create([
        'cliente_id' => $cliente->id,
        'tecnico_id' => $tecnico?->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::EmAnalise,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Nao arranca.',
    ]);
}

test('avancar de em_diagnostico para aguarda_pecas falha com checklist incompleta', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = criarTicketEmDiagnostico();

    $response = $this->actingAs($admin)->patchJson("/api/admin/tickets/{$ticket->id}/estado", [
        'estado' => 'aguarda_pecas',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('message', 'Checklist de diagnóstico incompleta.');
    expect($ticket->fresh()->estado)->toBe(TicketEstado::EmAnalise);
});

test('avancar de em_diagnostico para aguarda_pecas funciona com checklist completa', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = criarTicketEmDiagnostico();

    foreach (array_keys(config('checklists')['hardware']) as $itemChave) {
        $ticket->checklistRespostas()->create([
            'item_chave' => $itemChave,
            'concluido' => true,
            'concluido_por_user_id' => $admin->id,
            'concluido_at' => now(),
        ]);
    }

    $response = $this->actingAs($admin)->patchJson("/api/admin/tickets/{$ticket->id}/estado", [
        'estado' => 'aguarda_pecas',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('ticket.estado', 'aguarda_pecas');
});

test('outras transicoes nao sao bloqueadas por checklist incompleta', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = criarTicketEmDiagnostico();

    $response = $this->actingAs($admin)->patchJson("/api/admin/tickets/{$ticket->id}/estado", [
        'estado' => 'cancelado',
    ]);

    $response->assertStatus(200);
});

test('avancar de em_reparacao para reparacao_concluida falha com issue pendente', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = criarTicketEmDiagnostico();
    $ticket->update(['estado' => TicketEstado::EmCurso]);
    $ticket->issues()->create(['descricao' => 'Ventoinha ruidosa']);

    $response = $this->actingAs($admin)->patchJson("/api/admin/tickets/{$ticket->id}/estado", [
        'estado' => 'reparacao_concluida',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('message', 'Existem issues por resolver.');
});

test('avancar de em_reparacao para reparacao_concluida funciona sem issues pendentes', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = criarTicketEmDiagnostico();
    $ticket->update(['estado' => TicketEstado::EmCurso]);
    $issue = $ticket->issues()->create(['descricao' => 'Ventoinha ruidosa']);
    $issue->update(['resultado' => 'resolvido', 'resolvido_por_user_id' => $admin->id, 'resolvido_at' => now()]);

    $response = $this->actingAs($admin)->patchJson("/api/admin/tickets/{$ticket->id}/estado", [
        'estado' => 'reparacao_concluida',
    ]);

    $response->assertStatus(200);
});

test('avancar de em_reparacao para reparacao_concluida funciona sem issues registadas', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = criarTicketEmDiagnostico();
    $ticket->update(['estado' => TicketEstado::EmCurso]);

    $response = $this->actingAs($admin)->patchJson("/api/admin/tickets/{$ticket->id}/estado", [
        'estado' => 'reparacao_concluida',
    ]);

    $response->assertStatus(200);
});
