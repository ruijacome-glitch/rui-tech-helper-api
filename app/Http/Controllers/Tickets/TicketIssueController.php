<?php

namespace App\Http\Controllers\Tickets;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketIssue;
use Illuminate\Http\Request;

class TicketIssueController extends Controller
{
    public function store(Request $request, Ticket $ticket)
    {
        if ($request->user()->role === UserRole::Tecnico) {
            abort_if($ticket->tecnico_id !== $request->user()->id, 403);
        }

        $data = $request->validate([
            'descricao' => ['required', 'string'],
        ]);

        $issue = $ticket->issues()->create($data);

        return response()->json(['issue' => $issue], 201);
    }

    public function update(Request $request, Ticket $ticket, TicketIssue $issue)
    {
        if ($request->user()->role === UserRole::Tecnico) {
            abort_if($ticket->tecnico_id !== $request->user()->id, 403);
        }

        abort_if($issue->ticket_id !== $ticket->id, 404);

        $data = $request->validate([
            'resultado' => ['required', 'in:resolvido,nao_resolvido'],
            'observacao' => ['nullable', 'string'],
        ]);

        $issue->update([
            ...$data,
            'resolvido_por_user_id' => $request->user()->id,
            'resolvido_at' => now(),
        ]);

        return response()->json(['issue' => $issue->fresh()]);
    }
}
