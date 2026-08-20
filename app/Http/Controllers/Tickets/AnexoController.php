<?php

namespace App\Http\Controllers\Tickets;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketAnexo;
use Illuminate\Http\Request;

class AnexoController extends Controller
{
    public function store(Request $request, Ticket $ticket)
    {
        if ($request->user()->role === UserRole::Tecnico) {
            abort_if($ticket->tecnico_id !== $request->user()->id, 403);
        }

        $request->validate([
            'ficheiro' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $file = $request->file('ficheiro');
        $path = $file->store("anexos/{$ticket->id}", 'local');

        $anexo = TicketAnexo::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'path' => $path,
            'nome_original' => $file->getClientOriginalName(),
            'content_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return response()->json(['anexo' => $anexo], 201);
    }
}
