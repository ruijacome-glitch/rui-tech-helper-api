<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PagamentoEstado;
use App\Enums\TicketEstado;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Pagamento;
use App\Models\Ticket;

class DashboardController extends Controller
{
    public function index()
    {
        $inicioMes = now()->startOfMonth();
        $inicioSemana = now()->startOfWeek();

        $porEstado = collect(TicketEstado::cases())->mapWithKeys(
            fn (TicketEstado $estado) => [$estado->value => Ticket::where('estado', $estado)->count()]
        );

        $faturacaoMes = Pagamento::query()
            ->where('estado', PagamentoEstado::Pago)
            ->where('created_at', '>=', $inicioMes)
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
