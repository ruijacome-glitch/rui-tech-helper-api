<?php

namespace App\Http\Controllers\Public;

use App\Enums\PagamentoEstado;
use App\Enums\PagamentoOrigem;
use App\Http\Controllers\Controller;
use App\Jobs\EmitirFacturaRecibo;
use App\Mail\PagamentoRecebido;
use App\Models\Pagamento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WebhookController extends Controller
{
    public function ifthenpay(Request $request)
    {
        $chaveEsperada = config('services.ifthenpay.antiphishing_key');
        abort_if(blank($chaveEsperada) || ! hash_equals((string) $chaveEsperada, (string) $request->input('chave')), 403);

        $pagamento = Pagamento::where('referencia', $request->input('referencia'))
            ->when($request->filled('requestid'), fn ($query) => $query->orWhere('ifthenpay_request_id', $request->input('requestid')))
            ->first();

        abort_if($pagamento === null, 404);

        DB::transaction(function () use ($pagamento) {
            $locked = Pagamento::whereKey($pagamento->id)->lockForUpdate()->firstOrFail();

            if ($locked->estado === PagamentoEstado::Pago) {
                return;
            }

            $locked->update([
                'estado' => PagamentoEstado::Pago,
                'origem' => PagamentoOrigem::Ifthenpay,
                'paid_at' => now(),
            ]);

            $pago = $locked->fresh();

            EmitirFacturaRecibo::dispatch($pago);

            $cliente = $pago->orcamento->ticket->cliente;

            if ($cliente->email) {
                try {
                    Mail::to($cliente->email)->send(new PagamentoRecebido($pago));
                } catch (\Throwable $e) {
                    Log::error('Falha a enviar email de pagamento recebido', [
                        'pagamento_id' => $pago->id,
                        'erro' => $e->getMessage(),
                    ]);
                }
            }
        });

        return response()->json(['ok' => true]);
    }
}
