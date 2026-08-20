<?php

use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Enums\UserRole;
use App\Models\Cliente;
use App\Models\Orcamento;
use App\Models\Ticket;
use App\Models\User;

function criarTicketParaOrcamento(): Ticket
{
    $clienteUser = User::factory()->create(['role' => UserRole::Cliente]);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Teste',
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
        'descricao' => 'Computador nao arranca.',
    ]);
}

test('total soma quantidade x preco_unitario dos itens', function () {
    $ticket = criarTicketParaOrcamento();
    $orcamento = Orcamento::create([
        'ticket_id' => $ticket->id,
        'versao' => 1,
        'estado' => 'pendente',
    ]);
    $orcamento->itens()->create(['descricao' => 'Fonte de alimentacao', 'quantidade' => 1, 'preco_unitario' => 45.50]);
    $orcamento->itens()->create(['descricao' => 'Mao de obra', 'quantidade' => 2, 'preco_unitario' => 20.00]);

    expect($orcamento->fresh('itens')->total())->toBe(85.50);
});

test('proximaVersao incrementa a partir da ultima versao do ticket', function () {
    $ticket = criarTicketParaOrcamento();
    Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => 'rejeitado']);

    expect(Orcamento::proximaVersao($ticket))->toBe(2);
});

test('proximaVersao devolve 1 quando nao ha orcamentos', function () {
    $ticket = criarTicketParaOrcamento();

    expect(Orcamento::proximaVersao($ticket))->toBe(1);
});
