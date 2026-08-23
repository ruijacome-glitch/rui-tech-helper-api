<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PagamentoEstado;
use App\Enums\TicketEstado;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Pagamento;
use App\Models\Ticket;

final class DashboardController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $inicioMes = now()->startOfMonth();
        $inicioSemana = now()->startOfWeek();

        $contagensPorEstado = Ticket::query()
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $porEstado = collect(TicketEstado::cases())->mapWithKeys(
            fn (TicketEstado $estado) => [$estado->value => $contagensPorEstado->get($estado->value, 0)]
        );

        $faturacaoMes = Pagamento::query()
            ->where('estado', PagamentoEstado::Pago)
            ->where('paid_at', '>=', $inicioMes)
            ->sum('valor');

        $intervencoesRecentes = Ticket::with('cliente')
            ->latest()
            ->limit(5)
            ->get();

        return response()->json([
            'clientes' => [
                'total' => Cliente::count(),
                'novos_mes' => Cliente::where('created_at', '>=', $inicioMes)->count(),
            ],
            'intervencoes' => [
                'total' => Ticket::count(),
                'esta_semana' => Ticket::where('created_at', '>=', $inicioSemana)->count(),
            ],
            'faturacao_mes' => number_format((float) $faturacaoMes, 2, '.', ''),
            'pendentes' => Ticket::whereNotIn('estado', [TicketEstado::Resolvido, TicketEstado::Cancelado])->count(),
            // TODO: placeholder until agendamentos module exists
            'agendamentos' => ['total' => 0],
            'por_estado' => $porEstado,
            'intervencoes_recentes' => $intervencoesRecentes->map(fn (Ticket $t) => [
                'id' => $t->id,
                'titulo' => $t->titulo,
                'cliente_nome' => $t->cliente->nome,
                'estado' => $t->estado->value,
                'created_at' => $t->created_at,
            ]),
        ]);
    }
}
