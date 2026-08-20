<?php

namespace App\Http\Controllers\Tickets;

use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'cliente_id' => ['required', 'exists:clientes,id'],
            'tecnico_id' => ['nullable', 'exists:users,id'],
            'categoria' => ['required', 'in:hardware,software,rede,backup'],
            'prioridade' => ['required', 'in:urgente,normal,baixa'],
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['required', 'string'],
        ]);

        $ticket = Ticket::create([
            ...$data,
            'estado' => TicketEstado::Aberto,
            'origem' => TicketOrigem::Admin,
        ]);

        return response()->json(['ticket' => $ticket], 201);
    }

    public function storeCliente(Request $request)
    {
        $cliente = $request->user()->cliente;
        abort_if($cliente === null, 409, 'Utilizador nao tem ficha de cliente associada.');

        $data = $request->validate([
            'categoria' => ['required', 'in:hardware,software,rede,backup'],
            'prioridade' => ['required', 'in:urgente,normal,baixa'],
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['required', 'string'],
        ]);

        $ticket = Ticket::create([
            ...$data,
            'cliente_id' => $cliente->id,
            'estado' => TicketEstado::Aberto,
            'origem' => TicketOrigem::Cliente,
        ]);

        return response()->json(['ticket' => $ticket], 201);
    }
}
