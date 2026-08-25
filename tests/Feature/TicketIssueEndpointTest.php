<?php

use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\User;

function criarTicketComTecnicoParaIssue(?User $tecnico = null): Ticket
{
    $clienteUser = User::factory()->create(['role' => 'cliente']);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Issue',
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

test('admin cria issue no ticket', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = criarTicketComTecnicoParaIssue();

    $response = $this->actingAs($admin)->postJson("/api/admin/tickets/{$ticket->id}/issues", [
        'descricao' => 'Fonte de alimentacao morta',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('issue.resultado', 'pendente');
});

test('tecnico atribuido marca issue como resolvida', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketComTecnicoParaIssue($tecnico);
    $issue = $ticket->issues()->create(['descricao' => 'Fonte morta']);

    $response = $this->actingAs($tecnico)->patchJson("/api/tecnico/tickets/{$ticket->id}/issues/{$issue->id}", [
        'resultado' => 'resolvido',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('issue.resultado', 'resolvido');
    $response->assertJsonPath('issue.resolvido_por_user_id', $tecnico->id);
});

test('tecnico nao atribuido nao pode marcar issue', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $outroTecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketComTecnicoParaIssue($outroTecnico);
    $issue = $ticket->issues()->create(['descricao' => 'Fonte morta']);

    $response = $this->actingAs($tecnico)->patchJson("/api/tecnico/tickets/{$ticket->id}/issues/{$issue->id}", [
        'resultado' => 'resolvido',
    ]);

    $response->assertStatus(403);
});

test('issue de outro ticket devolve 404', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ticketA = criarTicketComTecnicoParaIssue();
    $ticketB = criarTicketComTecnicoParaIssue();
    $issueDoTicketB = $ticketB->issues()->create(['descricao' => 'Outro problema']);

    $response = $this->actingAs($admin)->patchJson("/api/admin/tickets/{$ticketA->id}/issues/{$issueDoTicketB->id}", [
        'resultado' => 'resolvido',
    ]);

    $response->assertStatus(404);
});

test('resultado invalido falha validacao', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = criarTicketComTecnicoParaIssue();
    $issue = $ticket->issues()->create(['descricao' => 'Fonte morta']);

    $response = $this->actingAs($admin)->patchJson("/api/admin/tickets/{$ticket->id}/issues/{$issue->id}", [
        'resultado' => 'invalido',
    ]);

    $response->assertStatus(422);
});
