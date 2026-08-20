<?php

use App\Enums\PagamentoEstado;
use App\Jobs\EmitirFacturaRecibo;
use App\Models\Pagamento;
use Illuminate\Support\Facades\Bus;

test('callback valido marca pagamento como pago e despacha job', function () {
    config(['services.ifthenpay.antiphishing_key' => 'chave-secreta']);
    Bus::fake();
    [, $orcamento] = criarOrcamentoAprovadoComPagamento();
    $pagamento = $orcamento->pagamento;
    $pagamento->update(['referencia' => '999888777']);

    $response = $this->postJson('/api/webhooks/ifthenpay', [
        'chave' => 'chave-secreta',
        'referencia' => '999888777',
        'requestid' => 'req-x',
    ]);

    $response->assertStatus(200);
    expect($pagamento->fresh()->estado->value)->toBe('pago');
    expect($pagamento->fresh()->origem->value)->toBe('ifthenpay');
    Bus::assertDispatched(EmitirFacturaRecibo::class);
});

test('callback com chave invalida e rejeitado e nao altera pagamento', function () {
    config(['services.ifthenpay.antiphishing_key' => 'chave-secreta']);
    [, $orcamento] = criarOrcamentoAprovadoComPagamento();
    $pagamento = $orcamento->pagamento;
    $pagamento->update(['referencia' => '999888777']);

    $response = $this->postJson('/api/webhooks/ifthenpay', [
        'chave' => 'chave-errada',
        'referencia' => '999888777',
    ]);

    $response->assertStatus(403);
    expect($pagamento->fresh()->estado->value)->toBe('pendente');
});

test('callback sem match e sem requestid nao apanha pagamento errado', function () {
    config(['services.ifthenpay.antiphishing_key' => 'chave-secreta']);
    [, $orcamento1] = criarOrcamentoAprovadoComPagamento();
    [, $orcamento2] = criarOrcamentoAprovadoComPagamento();
    $orcamento1->pagamento->update(['referencia' => '111111111']);
    $orcamento2->pagamento->update(['referencia' => '222222222']);

    $response = $this->postJson('/api/webhooks/ifthenpay', [
        'chave' => 'chave-secreta',
        'referencia' => 'nao-existe',
    ]);

    $response->assertStatus(404);
    expect($orcamento1->pagamento->fresh()->estado->value)->toBe('pendente');
    expect($orcamento2->pagamento->fresh()->estado->value)->toBe('pendente');
});

test('callback e rejeitado quando chave anti-phishing nao esta configurada mesmo sem chave no pedido', function () {
    config(['services.ifthenpay.antiphishing_key' => null]);
    [, $orcamento] = criarOrcamentoAprovadoComPagamento();
    $pagamento = $orcamento->pagamento;
    $pagamento->update(['referencia' => '999888777']);

    $response = $this->postJson('/api/webhooks/ifthenpay', [
        'referencia' => '999888777',
    ]);

    $response->assertStatus(403);
    expect($pagamento->fresh()->estado->value)->toBe('pendente');
});

test('callback e rejeitado quando chave anti-phishing nao esta configurada mesmo com chave null no pedido', function () {
    config(['services.ifthenpay.antiphishing_key' => null]);
    [, $orcamento] = criarOrcamentoAprovadoComPagamento();
    $pagamento = $orcamento->pagamento;
    $pagamento->update(['referencia' => '999888777']);

    $response = $this->postJson('/api/webhooks/ifthenpay', [
        'chave' => null,
        'referencia' => '999888777',
    ]);

    $response->assertStatus(403);
    expect($pagamento->fresh()->estado->value)->toBe('pendente');
});

test('callback duplicado em pagamento ja pago e no-op', function () {
    config(['services.ifthenpay.antiphishing_key' => 'chave-secreta']);
    Bus::fake();
    [, $orcamento] = criarOrcamentoAprovadoComPagamento();
    $pagamento = $orcamento->pagamento;
    $pagamento->update(['referencia' => '999888777', 'estado' => PagamentoEstado::Pago, 'paid_at' => now()]);

    $response = $this->postJson('/api/webhooks/ifthenpay', [
        'chave' => 'chave-secreta',
        'referencia' => '999888777',
    ]);

    $response->assertStatus(200);
    Bus::assertNotDispatched(EmitirFacturaRecibo::class);
});
