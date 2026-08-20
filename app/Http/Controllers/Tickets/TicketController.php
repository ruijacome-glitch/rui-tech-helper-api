<?php

namespace App\Http\Controllers\Tickets;

use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'cliente_id' => ['required', 'exists:clientes,id'],
            'tecnico_id' => ['nullable', Rule::exists('users', 'id')->where('role', UserRole::Tecnico->value)],
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

    public function updateEstado(Request $request, Ticket $ticket)
    {
        if ($request->user()->role === UserRole::Tecnico) {
            abort_if($ticket->tecnico_id !== $request->user()->id, 403);
        }

        $data = $request->validate([
            'estado' => ['required', 'in:aberto,em_analise,em_curso,aguarda_cliente,aguarda_peca,em_testes,resolvido,cancelado'],
            'observacao' => ['nullable', 'string'],
            'observacao_visivel_cliente' => ['boolean'],
        ]);

        $evento = $ticket->mudarEstado(
            $request->user(),
            TicketEstado::from($data['estado']),
            $data['observacao'] ?? null,
            $data['observacao_visivel_cliente'] ?? false,
        );

        return response()->json(['ticket' => $ticket->fresh(), 'evento' => $evento]);
    }

    public function show(Request $request, Ticket $ticket)
    {
        $cliente = $request->user()->cliente;
        abort_if($cliente === null || $ticket->cliente_id !== $cliente->id, 403);

        $eventos = $ticket->eventos()->orderBy('created_at')->get()->map(fn ($evento) => [
            'estado_anterior' => $evento->estado_anterior->value,
            'estado_novo' => $evento->estado_novo->value,
            'observacao' => $evento->observacao_visivel_cliente ? $evento->observacao : null,
            'created_at' => $evento->created_at,
        ]);

        $ticketArray = $ticket->toArray();
        $ticketArray['eventos'] = $eventos;

        return response()->json(['ticket' => $ticketArray]);
    }
}
