<?php
use App\Models\MoloniCredential;
use App\Models\Pagamento;
use App\Services\MoloniService;
use Illuminate\Support\Facades\Http;

test('trocarCodigoPorToken grava access_token e refresh_token', function () {
    Http::fake([
        'api.moloni.pt/v1/grant/' => Http::response([
            'access_token' => 'token-1',
            'refresh_token' => 'refresh-1',
            'expires_in' => 3600,
        ], 200),
    ]);

    $credential = (new MoloniService)->trocarCodigoPorToken('codigo-123', 'https://api.oruidoscomputadores.pt/api/webhooks/moloni/callback');

    expect($credential->access_token)->toBe('token-1');
    expect($credential->refresh_token)->toBe('refresh-1');
    expect(MoloniCredential::count())->toBe(1);
});

test('garantirToken renova quando expirado', function () {
    MoloniCredential::create(['access_token' => 'antigo', 'refresh_token' => 'refresh-antigo', 'expires_at' => now()->subMinute()]);
    Http::fake([
        'api.moloni.pt/v1/grant/' => Http::response(['access_token' => 'novo', 'refresh_token' => 'refresh-novo', 'expires_in' => 3600], 200),
    ]);

    $credential = (new MoloniService)->garantirToken();

    expect($credential->access_token)->toBe('novo');
});

test('garantirToken nao renova quando ainda valido', function () {
    MoloniCredential::create(['access_token' => 'valido', 'refresh_token' => 'r', 'expires_at' => now()->addHour()]);
    Http::fake();

    $credential = (new MoloniService)->garantirToken();

    expect($credential->access_token)->toBe('valido');
    Http::assertNothingSent();
});

test('criarFacturaRecibo envia dados do cliente e itens e grava documento', function () {
    config(['fiscal.isento_iva' => true, 'fiscal.motivo_isencao' => 'Isento de IVA - artigo 53']);
    MoloniCredential::create(['access_token' => 'token-1', 'refresh_token' => 'r', 'expires_at' => now()->addHour()]);
    Http::fake([
        'api.moloni.pt/v1/invoiceReceipts/insert/*' => Http::response([
            'document_id' => 'doc-1',
            'document_set_name' => 'FR',
            'number' => '2026/1',
            'pdf_url' => 'https://moloni.pt/doc-1.pdf',
        ], 200),
    ]);

    [, $orcamento, $pagamento] = criarOrcamentoAprovadoComPagamento();
    $orcamento->itens()->create(['descricao' => 'Fonte', 'quantidade' => 1, 'preco_unitario' => 45.50]);

    $documento = (new MoloniService)->criarFacturaRecibo($pagamento->fresh());

    expect($documento['document_id'])->toBe('doc-1');
    expect($documento['numero_documento'])->toBe('FR 2026/1');
    expect($documento['pdf_url'])->toBe('https://moloni.pt/doc-1.pdf');

    Http::assertSent(function ($request) {
        return str_contains((string) $request->url(), 'invoiceReceipts/insert')
            && $request['customer_vat'] === '123456789';
    });
});
