<?php

use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Orcamento;
use App\Models\Ticket;
use App\Models\User;

function criarTicketComOrcamentoParaTracking(string $nif = '123456789'): Ticket
{
    $clienteUser = User::factory()->create(['role' => 'cliente']);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Tracking Publico',
        'email' => $clienteUser->email,
        'telefone' => '912345678',
        'nif' => $nif,
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

test('cliente aprova orcamento via tracking token com nif correto', function () {
    $ticket = criarTicketComOrcamentoParaTracking('123456789');
    $orcamento = Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => 'pendente']);

    $response = $this->postJson("/api/public/tracking/{$ticket->tracking_token}/orcamentos/{$orcamento->id}/decisao", [
        'decisao' => 'aprovado',
        'nif' => '123456789',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('orcamento.estado', 'aprovado');
});

test('nif errado devolve 422', function () {
    $ticket = criarTicketComOrcamentoParaTracking('123456789');
    $orcamento = Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => 'pendente']);

    $response = $this->postJson("/api/public/tracking/{$ticket->tracking_token}/orcamentos/{$orcamento->id}/decisao", [
        'decisao' => 'aprovado',
        'nif' => '999999999',
    ]);

    $response->assertStatus(422);
});

test('token de outro ticket nao pode decidir orcamento', function () {
    $ticket = criarTicketComOrcamentoParaTracking('123456789');
    $orcamento = Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => 'pendente']);
    $outroTicket = criarTicketComOrcamentoParaTracking('987654321');

    $response = $this->postJson("/api/public/tracking/{$outroTicket->tracking_token}/orcamentos/{$orcamento->id}/decisao", [
        'decisao' => 'aprovado',
        'nif' => '123456789',
    ]);

    $response->assertStatus(404);
});

test('orcamento ja decidido devolve 409', function () {
    $ticket = criarTicketComOrcamentoParaTracking('123456789');
    $orcamento = Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => 'aprovado', 'decided_at' => now()]);

    $response = $this->postJson("/api/public/tracking/{$ticket->tracking_token}/orcamentos/{$orcamento->id}/decisao", [
        'decisao' => 'rejeitado',
        'nif' => '123456789',
    ]);

    $response->assertStatus(409);
});
