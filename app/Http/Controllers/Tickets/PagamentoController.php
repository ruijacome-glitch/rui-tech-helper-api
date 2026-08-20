<?php

namespace App\Http\Controllers\Tickets;

use App\Enums\PagamentoEstado;
use App\Enums\PagamentoOrigem;
use App\Http\Controllers\Controller;
use App\Jobs\EmitirFacturaRecibo;
use App\Models\Orcamento;
use App\Services\IfthenPayService;
use Illuminate\Http\Request;

class PagamentoController extends Controller
{
    public function store(Request $request, Orcamento $orcamento, IfthenPayService $ifthenPay)
    {
        $cliente = $request->user()->cliente;
        abort_if($cliente === null || $orcamento->ticket->cliente_id !== $cliente->id, 403);

        $pagamento = $orcamento->pagamento;
        abort_if($pagamento === null, 409, 'Orcamento ainda nao foi aprovado.');

        if ($pagamento->estado === PagamentoEstado::Pago) {
            return response()->json(['pagamento' => $pagamento], 200);
        }

        if ($pagamento->estado === PagamentoEstado::Pendente && $pagamento->expires_at !== null && ! $pagamento->estaExpirado()) {
            return response()->json(['pagamento' => $pagamento], 200);
        }

        $data = $request->validate([
            'metodo' => ['required', 'in:mb,mbway'],
            'telefone' => ['required_if:metodo,mbway', 'nullable', 'string', 'max:20'],
        ]);

        $pagamento = $data['metodo'] === 'mb'
            ? $ifthenPay->gerarReferenciaMb($pagamento)
            : $ifthenPay->gerarPedidoMbway($pagamento, $data['telefone']);

        return response()->json(['pagamento' => $pagamento], 201);
    }

    public function marcarPago(Orcamento $orcamento)
    {
        $pagamento = $orcamento->pagamento;
        abort_if($pagamento === null, 409, 'Orcamento ainda nao foi aprovado.');
        abort_if($pagamento->estado === PagamentoEstado::Pago, 409, 'Pagamento ja confirmado.');

        $pagamento->update([
            'estado' => PagamentoEstado::Pago,
            'origem' => PagamentoOrigem::Manual,
            'paid_at' => now(),
        ]);

        EmitirFacturaRecibo::dispatch($pagamento->fresh());

        return response()->json(['pagamento' => $pagamento->fresh()]);
    }
}
