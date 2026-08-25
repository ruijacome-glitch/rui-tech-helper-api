<?php

use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\User;

function criarTicketParaTracking(): Ticket
{
    $clienteUser = User::factory()->create(['role' => 'cliente']);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Tracking Publico',
        'email' => $clienteUser->email,
        'telefone' => '912345678',
    ]);

    return Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Nao arranca.',
    ]);
}

test('token valido devolve dados do ticket sem autenticacao', function () {
    $ticket = criarTicketParaTracking();

    $response = $this->getJson("/api/public/tracking/{$ticket->tracking_token}");

    $response->assertStatus(200);
    $response->assertJsonPath('ticket.id', $ticket->id);
    $response->assertJsonStructure(['ticket' => ['issues', 'checklist']]);
});

test('token invalido devolve 404', function () {
    $response = $this->getJson('/api/public/tracking/'.\Illuminate\Support\Str::uuid());

    $response->assertStatus(404);
});

test('resposta nao inclui tracking_token no corpo', function () {
    $ticket = criarTicketParaTracking();

    $response = $this->getJson("/api/public/tracking/{$ticket->tracking_token}");

    $response->assertStatus(200);
    $response->assertJsonMissingPath('ticket.tracking_token');
});

test('resposta nao inclui ids de staff em issues e checklist', function () {
    $ticket = criarTicketParaTracking();
    $tecnico = User::factory()->create(['role' => 'tecnico']);

    $ticket->issues()->create([
        'descricao' => 'Fonte de alimentacao morta',
        'resultado' => 'resolvido',
        'resolvido_por_user_id' => $tecnico->id,
        'resolvido_at' => now(),
    ]);

    $ticket->checklistRespostas()->create([
        'item_chave' => 'testar-fonte-alimentacao',
        'concluido' => true,
        'concluido_por_user_id' => $tecnico->id,
        'concluido_at' => now(),
    ]);

    $response = $this->getJson("/api/public/tracking/{$ticket->tracking_token}");

    $response->assertStatus(200);
    $response->assertJsonMissingPath('ticket.issues.0.resolvido_por_user_id');
    $response->assertJsonMissingPath('ticket.checklist.0.concluido_por_user_id');
    expect($response->getContent())
        ->not->toContain('resolvido_por_user_id')
        ->not->toContain('concluido_por_user_id');
});
