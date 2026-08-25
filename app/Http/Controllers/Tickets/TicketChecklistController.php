<?php

namespace App\Http\Controllers\Tickets;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketChecklistController extends Controller
{
    public function toggle(Request $request, Ticket $ticket, string $itemChave)
    {
        if ($request->user()->role === UserRole::Tecnico) {
            abort_if($ticket->tecnico_id !== $request->user()->id, 403);
        }

        $itensCategoria = config('checklists')[$ticket->categoria->value] ?? [];
        abort_if(! array_key_exists($itemChave, $itensCategoria), 422);

        $resposta = DB::transaction(function () use ($ticket, $itemChave, $request) {
            $existente = $ticket->checklistRespostas()
                ->where('item_chave', $itemChave)
                ->lockForUpdate()
                ->first();

            abort_if($existente && $existente->concluido, 409);

            try {
                return $ticket->checklistRespostas()->updateOrCreate(
                    ['item_chave' => $itemChave],
                    [
                        'concluido' => true,
                        'concluido_por_user_id' => $request->user()->id,
                        'concluido_at' => now(),
                    ]
                );
            } catch (QueryException $e) {
                if ($e->getCode() === '23000') {
                    abort(409);
                }

                throw $e;
            }
        });

        return response()->json(['resposta' => $resposta]);
    }
}
