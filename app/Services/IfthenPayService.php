<?php

namespace App\Services;

use App\Enums\PagamentoEstado;
use App\Enums\PagamentoMetodo;
use App\Models\Pagamento;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IfthenPayService
{
    private const MB_BASE = 'https://api.ifthenpay.com/multibanco/reference';
    private const MBWAY_ENDPOINT = 'https://api.ifthenpay.com/spg/payment/mbway';

    private function mbEndpoint(): string
    {
        return self::MB_BASE.(config('services.ifthenpay.sandbox') ? '/sandbox' : '/init');
    }

    public function gerarReferenciaMb(Pagamento $pagamento): Pagamento
    {
        try {
            $response = Http::asJson()->timeout(10)->connectTimeout(5)->post($this->mbEndpoint(), [
                'mbKey' => config('services.ifthenpay.mb_key'),
                'orderId' => (string) $pagamento->orcamento_id,
                'amount' => number_format((float) $pagamento->valor, 2, '.', ''),
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new RuntimeException('Falha de ligacao ao gerar referencia Multibanco: '.$e->getMessage(), previous: $e);
        }

        $body = $response->json();

        if (! $response->successful() || empty($body['Entity']) || empty($body['Reference'])) {
            throw new RuntimeException('Falha ao gerar referencia Multibanco: '.($body['Message'] ?? 'erro desconhecido'));
        }

        $pagamento->update([
            'metodo' => PagamentoMetodo::Mb,
            'estado' => PagamentoEstado::Pendente,
            'entidade' => $body['Entity'],
            'referencia' => $body['Reference'],
            'ifthenpay_request_id' => $body['RequestId'] ?? null,
            'telefone' => null,
            'expires_at' => now()->addHours(48),
        ]);

        return $pagamento->fresh();
    }

    public function gerarPedidoMbway(Pagamento $pagamento, string $telefone): Pagamento
    {
        try {
            $response = Http::asJson()->timeout(10)->connectTimeout(5)->post(self::MBWAY_ENDPOINT, [
                'mbWayKey' => config('services.ifthenpay.mbway_key'),
                'orderId' => (string) $pagamento->orcamento_id,
                'amount' => number_format((float) $pagamento->valor, 2, '.', ''),
                'mobileNumber' => $this->formatarTelefone($telefone),
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new RuntimeException('Falha de ligacao ao gerar pedido MB WAY: '.$e->getMessage(), previous: $e);
        }

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

    /**
     * IfthenPay MB WAY API exige formato "351#912345678" (indicativo + '#' + numero).
     * Aceita numero ja formatado, com/sem indicativo, com espacos/traços.
     */
    private function formatarTelefone(string $telefone): string
    {
        if (str_contains($telefone, '#')) {
            return $telefone;
        }

        $digitos = preg_replace('/\D/', '', $telefone);
        $digitos = ltrim($digitos, '0');

        if (str_starts_with($digitos, '351') && strlen($digitos) > 9) {
            return '351#'.substr($digitos, 3);
        }

        return '351#'.$digitos;
    }
}
