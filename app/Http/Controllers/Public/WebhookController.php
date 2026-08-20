<?php

namespace App\Http\Controllers\Public;

use App\Enums\PagamentoEstado;
use App\Enums\PagamentoOrigem;
use App\Http\Controllers\Controller;
use App\Jobs\EmitirFacturaRecibo;
use App\Models\Pagamento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebhookController extends Controller
{
    public function ifthenpay(Request $request)
    {
        abort_if($request->input('chave') !== config('services.ifthenpay.antiphishing_key'), 403);

        $pagamento = Pagamento::where('referencia', $request->input('referencia'))
            ->orWhere('ifthenpay_request_id', $request->input('requestid'))
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

            EmitirFacturaRecibo::dispatch($locked->fresh());
        });

        return response()->json(['ok' => true]);
    }
}
