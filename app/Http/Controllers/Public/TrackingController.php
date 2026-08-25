<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tickets\OrcamentoController;
use App\Http\Controllers\Tickets\TicketController;
use App\Models\Orcamento;
use App\Models\Ticket;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function show(string $token)
    {
        $ticket = Ticket::where('tracking_token', $token)->firstOrFail();

        $ticketArray = (new TicketController)->serializeTicketDetailCliente($ticket);

        return response()->json(['ticket' => $ticketArray]);
    }

    public function decisaoOrcamento(Request $request, string $token, Orcamento $orcamento)
    {
        $ticketDoToken = Ticket::where('tracking_token', $token)->first();
        abort_if(! $ticketDoToken || $orcamento->ticket_id !== $ticketDoToken->id, 404);

        $data = $request->validate([
            'decisao' => ['required', 'in:aprovado,rejeitado'],
            'nif' => ['required', 'string'],
        ]);

        $cliente = $ticketDoToken->cliente;
        abort_if($cliente === null || empty($cliente->nif) || $data['nif'] !== $cliente->nif, 422, 'NIF invalido.');

        $orcamento = (new OrcamentoController)->aplicarDecisao($orcamento, $data['decisao']);

        return response()->json(['orcamento' => $orcamento->fresh()]);
    }
}
