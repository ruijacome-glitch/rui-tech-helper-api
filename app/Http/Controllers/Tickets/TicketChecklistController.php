<?php

namespace App\Http\Controllers\Tickets;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;

class TicketChecklistController extends Controller
{
    public function toggle(Request $request, Ticket $ticket, string $itemChave)
    {
        if ($request->user()->role === UserRole::Tecnico) {
            abort_if($ticket->tecnico_id !== $request->user()->id, 403);
        }

        $existente = $ticket->checklistRespostas()->where('item_chave', $itemChave)->first();

        abort_if($existente && $existente->concluido, 409);

        $resposta = $ticket->checklistRespostas()->updateOrCreate(
            ['item_chave' => $itemChave],
            [
                'concluido' => true,
                'concluido_por_user_id' => $request->user()->id,
                'concluido_at' => now(),
            ]
        );

        return response()->json(['resposta' => $resposta]);
    }
}
