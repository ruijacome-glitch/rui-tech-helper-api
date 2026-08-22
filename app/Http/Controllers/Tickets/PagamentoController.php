<?php

namespace App\Http\Controllers\Tickets;

use App\Enums\PagamentoEstado;
use App\Enums\PagamentoOrigem;
use App\Http\Controllers\Controller;
use App\Jobs\EmitirFacturaRecibo;
use App\Models\Orcamento;
use App\Models\Pagamento;
use App\Services\IfthenPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PagamentoController extends Controller
{
    public function store(Request $request, Orcamento $orcamento, IfthenPayService $ifthenPay)
    {
        $cliente = $request->user()->cliente;
        abort_if($cliente === null || $orcamento->ticket->cliente_id !== $cliente->id, 403);

        $pagamento = $orcamento->pagamento;
        abort_if($pagamento === null, 409, 'Orcamento ainda nao foi aprovado.');

        $data = $request->validate([
            'metodo' => ['required', 'in:mb,mbway'],
            'telefone' => ['required_if:metodo,mbway', 'nullable', 'string', 'max:20'],
        ]);

        return DB::transaction(function () use ($pagamento, $data, $ifthenPay) {
            $locked = Pagamento::whereKey($pagamento->id)->lockForUpdate()->firstOrFail();

            if ($locked->estado === PagamentoEstado::Pago) {
                return response()->json(['pagamento' => $locked], 200);
            }

            if ($locked->estado === PagamentoEstado::Pendente && $locked->expires_at !== null && ! $locked->estaExpirado()) {
                return response()->json(['pagamento' => $locked], 200);
            }

            try {
                $resultado = $data['metodo'] === 'mb'
                    ? $ifthenPay->gerarReferenciaMb($locked)
                    : $ifthenPay->gerarPedidoMbway($locked, $data['telefone']);
            } catch (\RuntimeException $e) {
                abort(502, 'Nao foi possivel gerar a referencia de pagamento, tente novamente.');
            }

            return response()->json(['pagamento' => $resultado], 201);
        });
    }

    /**
     * Protected by role:admin route middleware (wired in Task 8) — matches the
     * convention used by other admin-only controllers in this codebase, which
     * rely on route-level middleware rather than an in-method role check.
     */
    public function marcarPago(Orcamento $orcamento)
    {
        $pagamento = $orcamento->pagamento;
        abort_if($pagamento === null, 409, 'Orcamento ainda nao foi aprovado.');

        return DB::transaction(function () use ($pagamento) {
            $locked = Pagamento::whereKey($pagamento->id)->lockForUpdate()->firstOrFail();

            abort_if($locked->estado === PagamentoEstado::Pago, 409, 'Pagamento ja confirmado.');

            $locked->update([
                'estado' => PagamentoEstado::Pago,
                'origem' => PagamentoOrigem::Manual,
                'paid_at' => now(),
            ]);

            EmitirFacturaRecibo::dispatch($locked->fresh());

            return response()->json(['pagamento' => $locked->fresh()]);
        });
    }

    public function indexAdmin(Request $request)
    {
        $data = $request->validate([
            'estado' => ['nullable', Rule::enum(PagamentoEstado::class)],
        ]);

        $pagamentos = Pagamento::query()
            ->with('orcamento.ticket:id,titulo,cliente_id')
            ->when($data['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $pagamentos->getCollection()->map(fn (Pagamento $p) => [
                'id' => $p->id,
                'estado' => $p->estado->value,
                'metodo' => $p->metodo?->value,
                'valor' => $p->valor,
                'paid_at' => $p->paid_at,
                'created_at' => $p->created_at,
                'orcamento' => [
                    'id' => $p->orcamento->id,
                    'versao' => $p->orcamento->versao,
                    'ticket' => [
                        'id' => $p->orcamento->ticket->id,
                        'titulo' => $p->orcamento->ticket->titulo,
                    ],
                ],
            ])->all(),
            'meta' => [
                'current_page' => $pagamentos->currentPage(),
                'last_page' => $pagamentos->lastPage(),
                'total' => $pagamentos->total(),
            ],
        ]);
    }
}
