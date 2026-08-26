<?php

use App\Enums\OrcamentoEstado;
use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Enums\UserRole;
use App\Mail\OrcamentoPronto;
use App\Models\Cliente;
use App\Models\Orcamento;
use App\Models\Ticket;
use App\Models\User;

test('email de orcamento pronto contem titulo do ticket e total', function () {
    config(['services.frontend_url' => 'https://oruidoscomputadores.pt']);

    $clienteUser = User::factory()->create(['role' => UserRole::Cliente]);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Teste',
        'email' => 'cliente@example.com',
        'telefone' => '912345678',
    ]);
    $ticket = Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Computador nao arranca.',
    ]);
    $orcamento = Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => OrcamentoEstado::Pendente]);
    $orcamento->itens()->create(['descricao' => 'Fonte', 'quantidade' => 1, 'preco_unitario' => 50.00]);

    $rendered = (new OrcamentoPronto($orcamento->fresh('itens')))->render();

    expect($rendered)->toContain('PC nao liga');
    expect($rendered)->toContain('50.00€');
    expect($rendered)->toContain('https://oruidoscomputadores.pt/portal/tickets/'.$ticket->id);
});

test('email de orcamento pronto usa layout com botao', function () {
    config(['services.frontend_url' => 'https://oruidoscomputadores.pt']);

    $clienteUser = User::factory()->create(['role' => UserRole::Cliente]);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Teste',
        'email' => 'cliente@example.com',
        'telefone' => '912345678',
    ]);
    $ticket = Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Computador nao arranca.',
    ]);
    $orcamento = Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => OrcamentoEstado::Pendente]);
    $orcamento->itens()->create(['descricao' => 'Fonte', 'quantidade' => 1, 'preco_unitario' => 50.00]);

    $rendered = (new OrcamentoPronto($orcamento->fresh('itens')))->render();

    expect($rendered)->toContain('Ver e aprovar orçamento');
    expect($rendered)->toContain('O Rui dos Computadores');
});
