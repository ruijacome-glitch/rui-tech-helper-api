<?php

use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\User;

function criarTicketParaSerializacaoStaff(): array
{
    $clienteUser = User::factory()->create(['role' => 'cliente']);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Staff Serialization',
        'email' => $clienteUser->email,
        'telefone' => '912345678',
    ]);
    $tecnico = User::factory()->create(['role' => 'tecnico', 'name' => 'Joao Tecnico']);

    $ticket = Ticket::create([
        'cliente_id' => $cliente->id,
        'tecnico_id' => $tecnico->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::EmAnalise,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Nao arranca.',
    ]);

    return [$ticket, $tecnico];
}

test('resposta admin inclui tracking_token', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    [$ticket] = criarTicketParaSerializacaoStaff();

    $response = $this->actingAs($admin)->getJson("/api/admin/tickets/{$ticket->id}");

    $response->assertOk();
    $response->assertJsonPath('ticket.tracking_token', $ticket->tracking_token);
});

test('resposta admin inclui issues com nome de quem resolveu', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    [$ticket, $tecnico] = criarTicketParaSerializacaoStaff();

    $issue = $ticket->issues()->create(['descricao' => 'Ventoinha ruidosa']);
    $issue->update([
        'resultado' => 'resolvido',
        'resolvido_por_user_id' => $tecnico->id,
        'resolvido_at' => now(),
    ]);

    $response = $this->actingAs($admin)->getJson("/api/admin/tickets/{$ticket->id}");

    $response->assertOk();
    $response->assertJsonPath('ticket.issues.0.descricao', 'Ventoinha ruidosa');
    $response->assertJsonPath('ticket.issues.0.resultado', 'resolvido');
    $response->assertJsonPath('ticket.issues.0.resolvido_por', 'Joao Tecnico');
});

test('resposta admin inclui checklist completa da categoria com nome de quem concluiu', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    [$ticket, $tecnico] = criarTicketParaSerializacaoStaff();

    $ticket->checklistRespostas()->create([
        'item_chave' => 'testar-fonte-alimentacao',
        'concluido' => true,
        'concluido_por_user_id' => $tecnico->id,
        'concluido_at' => now(),
    ]);

    $response = $this->actingAs($admin)->getJson("/api/admin/tickets/{$ticket->id}");

    $response->assertOk();
    $response->assertJsonCount(4, 'ticket.checklist');
    $response->assertJsonPath('ticket.checklist.0.item_chave', 'testar-fonte-alimentacao');
    $response->assertJsonPath('ticket.checklist.0.concluido', true);
    $response->assertJsonPath('ticket.checklist.0.concluido_por', 'Joao Tecnico');
    $response->assertJsonPath('ticket.checklist.1.concluido', false);
    $response->assertJsonPath('ticket.checklist.1.concluido_por', null);
});
