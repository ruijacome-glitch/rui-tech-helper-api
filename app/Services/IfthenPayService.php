<?php

namespace App\Services;

use App\Enums\PagamentoEstado;
use App\Enums\PagamentoMetodo;
use App\Models\Pagamento;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IfthenPayService
{
    private const MB_ENDPOINT = 'https://ifthenpay.com/api/multibanco/reference/init';
    private const MBWAY_ENDPOINT = 'https://ifthenpay.com/api/mbway/mb/wayrequest';

    public function gerarReferenciaMb(Pagamento $pagamento): Pagamento
    {
        $response = Http::asForm()->post(self::MB_ENDPOINT, [
            'mbKey' => config('services.ifthenpay.mb_key'),
            'orderId' => (string) $pagamento->orcamento_id,
            'amount' => number_format((float) $pagamento->valor, 2, '.', ''),
        ]);

        $body = $response->json();

        if (! $response->successful() || empty($body['Entidade']) || empty($body['Referencia'])) {
            throw new RuntimeException('Falha ao gerar referencia Multibanco: '.($body['Message'] ?? 'erro desconhecido'));
        }

        $pagamento->update([
            'metodo' => PagamentoMetodo::Mb,
            'estado' => PagamentoEstado::Pendente,
            'entidade' => $body['Entidade'],
            'referencia' => $body['Referencia'],
            'ifthenpay_request_id' => $body['RequestId'] ?? null,
            'telefone' => null,
            'expires_at' => now()->addHours(48),
        ]);

        return $pagamento->fresh();
    }

    public function gerarPedidoMbway(Pagamento $pagamento, string $telefone): Pagamento
    {
        $response = Http::asForm()->post(self::MBWAY_ENDPOINT, [
            'mbwaykey' => config('services.ifthenpay.mbway_key'),
            'orderid' => (string) $pagamento->orcamento_id,
            'amount' => number_format((float) $pagamento->valor, 2, '.', ''),
            'mobilenumber' => $telefone,
        ]);

        $body = $response->json();

        if (! $response->successful() || ($body['Status'] ?? null) !== '000') {
            throw new RuntimeException('Falha ao gerar pedido MB WAY: '.($body['Message'] ?? 'erro desconhecido'));
        }

        $pagamento->update([
            'metodo' => PagamentoMetodo::Mbway,
            'estado' => PagamentoEstado::Pendente,
            'entidade' => null,
            'referencia' => null,
            'telefone' => $telefone,
            'ifthenpay_request_id' => $body['RequestId'] ?? null,
            'expires_at' => now()->addHours(48),
        ]);

        return $pagamento->fresh();
    }
}
