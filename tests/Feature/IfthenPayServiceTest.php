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
use App\Services\IfthenPayService;
use Illuminate\Support\Facades\Http;

function criarPagamentoPendente(): Pagamento
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
    $orcamento = Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => 'aprovado', 'decided_at' => now()]);

    return Pagamento::create(['orcamento_id' => $orcamento->id, 'estado' => PagamentoEstado::Pendente, 'valor' => 45.50]);
}

test('gerarReferenciaMb grava entidade referencia e expiracao', function () {
    Http::fake([
        'ifthenpay.com/api/multibanco/reference/init' => Http::response([
            'Entidade' => '12345',
            'Referencia' => '123456789',
            'RequestId' => 'req-1',
            'Amount' => '45.50',
        ], 200),
    ]);

    $pagamento = criarPagamentoPendente();
    $resultado = (new IfthenPayService)->gerarReferenciaMb($pagamento);

    expect($resultado->metodo->value)->toBe('mb');
    expect($resultado->entidade)->toBe('12345');
    expect($resultado->referencia)->toBe('123456789');
    expect($resultado->ifthenpay_request_id)->toBe('req-1');
    expect($resultado->expires_at->diffInHours(now()))->toBeLessThanOrEqual(48);
});

test('gerarReferenciaMb lanca excecao quando ifthenpay falha', function () {
    Http::fake([
        'ifthenpay.com/api/multibanco/reference/init' => Http::response(['Message' => 'chave invalida'], 400),
    ]);

    $pagamento = criarPagamentoPendente();

    expect(fn () => (new IfthenPayService)->gerarReferenciaMb($pagamento))->toThrow(RuntimeException::class);
    expect($pagamento->fresh()->estado->value)->toBe('pendente');
    expect($pagamento->fresh()->entidade)->toBeNull();
});

test('gerarPedidoMbway grava telefone e pedido', function () {
    Http::fake([
        'ifthenpay.com/api/mbway/mb/wayrequest' => Http::response([
            'RequestId' => 'req-2',
            'Status' => '000',
            'Message' => 'Pedido enviado',
        ], 200),
    ]);

    $pagamento = criarPagamentoPendente();
    $resultado = (new IfthenPayService)->gerarPedidoMbway($pagamento, '912345678');

    expect($resultado->metodo->value)->toBe('mbway');
    expect($resultado->telefone)->toBe('912345678');
    expect($resultado->ifthenpay_request_id)->toBe('req-2');
});
