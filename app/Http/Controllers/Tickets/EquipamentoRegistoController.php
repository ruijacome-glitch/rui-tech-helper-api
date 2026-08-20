<?php
// app/Http/Controllers/Tickets/EquipamentoRegistoController.php
namespace App\Http\Controllers\Tickets;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\EquipamentoRegisto;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EquipamentoRegistoController extends Controller
{
    public function store(Request $request, Ticket $ticket)
    {
        if ($request->user()->role === UserRole::Tecnico) {
            abort_if($ticket->tecnico_id !== $request->user()->id, 403);
        }

        $data = $request->validate([
            'tipo' => ['required', 'in:entrega,devolucao'],
            'nome_assinante' => ['required', 'string', 'max:255'],
            'assinatura' => ['required', 'string', 'starts_with:data:image/png;base64,'],
            'observacoes' => ['nullable', 'string'],
        ]);

        abort_if(
            $ticket->equipamentoRegistos()->where('tipo', $data['tipo'])->exists(),
            409,
            'Ja existe registo deste tipo para o ticket.'
        );

        $base64 = substr($data['assinatura'], strlen('data:image/png;base64,'));
        $filename = "assinaturas/{$ticket->id}-{$data['tipo']}-" . Str::random(8) . '.png';
        Storage::disk('local')->put($filename, base64_decode($base64));

        $registo = EquipamentoRegisto::create([
            'ticket_id' => $ticket->id,
            'tipo' => $data['tipo'],
            'user_id' => $request->user()->id,
            'nome_assinante' => $data['nome_assinante'],
            'assinatura_path' => $filename,
            'observacoes' => $data['observacoes'] ?? null,
        ]);

        return response()->json(['registo' => $registo], 201);
    }
}
