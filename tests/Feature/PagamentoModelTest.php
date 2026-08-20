<?php

use App\Enums\PagamentoEstado;
use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Enums\UserRole;
use App\Models\Cliente;
use App\Models\Orcamento;
use App\Models\Pagamento;
use App\Models\Ticket;
use App\Models\User;

function criarOrcamentoParaPagamento(): Orcamento
{
    $clienteUser = User::factory()->create(['role' => UserRole::Cliente]);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Teste',
        'email' => $clienteUser->email,
        'telefone' => '912345678',
        'nif' => '123456789',
    ]);
    $ticket = Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Nao arranca.',
    ]);

    return Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => 'aprovado', 'decided_at' => now()]);
}

test('pagamento pendente com expires_at no passado fica expirado', function () {
    $orcamento = criarOrcamentoParaPagamento();
    $pagamento = Pagamento::create([
        'orcamento_id' => $orcamento->id,
        'estado' => PagamentoEstado::Pendente,
        'valor' => 50,
        'expires_at' => now()->subHour(),
    ]);

    expect($pagamento->estaExpirado())->toBeTrue();
    expect($pagamento->estado_efetivo)->toBe('expirado');
});

test('pagamento pendente sem expires_at nao esta expirado', function () {
    $orcamento = criarOrcamentoParaPagamento();
    $pagamento = Pagamento::create([
        'orcamento_id' => $orcamento->id,
        'estado' => PagamentoEstado::Pendente,
        'valor' => 50,
    ]);

    expect($pagamento->estaExpirado())->toBeFalse();
    expect($pagamento->estado_efetivo)->toBe('pendente');
});

test('pagamento pago nunca esta expirado mesmo com expires_at passado', function () {
    $orcamento = criarOrcamentoParaPagamento();
    $pagamento = Pagamento::create([
        'orcamento_id' => $orcamento->id,
        'estado' => PagamentoEstado::Pago,
        'valor' => 50,
        'expires_at' => now()->subHour(),
        'paid_at' => now(),
    ]);

    expect($pagamento->estaExpirado())->toBeFalse();
    expect($pagamento->estado_efetivo)->toBe('pago');
});
