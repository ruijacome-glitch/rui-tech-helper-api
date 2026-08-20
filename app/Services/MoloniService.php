<?php

namespace App\Services;

use App\Models\MoloniCredential;
use App\Models\Pagamento;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MoloniService
{
    private const TOKEN_ENDPOINT = 'https://api.moloni.pt/v1/grant/';
    private const DOCUMENT_ENDPOINT = 'https://api.moloni.pt/v1/invoiceReceipts/insert/';

    public function trocarCodigoPorToken(string $code, string $redirectUri): MoloniCredential
    {
        $response = Http::asForm()->post(self::TOKEN_ENDPOINT, [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.moloni.client_id'),
            'client_secret' => config('services.moloni.client_secret'),
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);

        $body = $response->json();

        if (! $response->successful() || empty($body['access_token'])) {
            throw new RuntimeException('Falha ao trocar codigo Moloni por token.');
        }

        return MoloniCredential::updateOrCreate(['id' => 1], [
            'access_token' => $body['access_token'],
            'refresh_token' => $body['refresh_token'],
            'expires_at' => now()->addSeconds((int) $body['expires_in']),
        ]);
    }

    public function garantirToken(): MoloniCredential
    {
        $credential = MoloniCredential::first();
        abort_if($credential === null, 500, 'Moloni nao autorizado. Executar fluxo OAuth inicial.');

        if ($credential->expires_at->isFuture()) {
            return $credential;
        }

        $response = Http::asForm()->post(self::TOKEN_ENDPOINT, [
            'grant_type' => 'refresh_token',
            'client_id' => config('services.moloni.client_id'),
            'client_secret' => config('services.moloni.client_secret'),
            'refresh_token' => $credential->refresh_token,
        ]);

        $body = $response->json();

        if (! $response->successful() || empty($body['access_token'])) {
            throw new RuntimeException('Falha ao renovar token Moloni.');
        }

        $credential->update([
            'access_token' => $body['access_token'],
            'refresh_token' => $body['refresh_token'],
            'expires_at' => now()->addSeconds((int) $body['expires_in']),
        ]);

        return $credential->fresh();
    }

    public function criarFacturaRecibo(Pagamento $pagamento): array
    {
        $credential = $this->garantirToken();
        $orcamento = $pagamento->orcamento()->with('itens', 'ticket.cliente')->first();
        $cliente = $orcamento->ticket->cliente;

        $isento = (bool) config('fiscal.isento_iva');
        $taxa = $isento ? 0 : (int) config('fiscal.iva_taxa');

        $produtos = $orcamento->itens->map(fn ($item) => [
            'name' => $item->descricao,
            'qty' => $item->quantidade,
            'price' => (float) $item->preco_unitario,
            'taxes' => $isento ? [] : [['taxId' => (int) config('services.moloni.iva_tax_id'), 'value' => $taxa]],
            'exemptionReason' => $isento ? config('fiscal.motivo_isencao') : null,
        ])->all();

        $response = Http::asForm()->post(self::DOCUMENT_ENDPOINT, [
            'access_token' => $credential->access_token,
            'company_id' => config('services.moloni.company_id'),
            'date' => now()->format('Y-m-d'),
            'expiration_date' => now()->format('Y-m-d'),
            'customer_name' => $cliente->nome,
            'customer_vat' => $cliente->nif,
            'customer_address' => $cliente->morada,
            'products' => $produtos,
            'status' => 1,
        ]);

        $body = $response->json();

        if (! $response->successful() || empty($body['document_id'])) {
            throw new RuntimeException('Falha ao criar Factura-Recibo na Moloni: '.($body['message'] ?? 'erro desconhecido'));
        }

        return [
            'document_id' => (string) $body['document_id'],
            'numero_documento' => $body['document_set_name'].' '.$body['number'],
            'pdf_url' => $body['pdf_url'] ?? null,
        ];
    }
}
