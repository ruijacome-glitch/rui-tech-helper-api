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
            'assinatura' => ['required', 'string', 'starts_with:data:image/png;base64,', 'max:2000000'],
            'observacoes' => ['nullable', 'string'],
        ]);

        abort_if(
            $ticket->equipamentoRegistos()->where('tipo', $data['tipo'])->exists(),
            409,
            'Ja existe registo deste tipo para o ticket.'
        );

        $base64 = substr($data['assinatura'], strlen('data:image/png;base64,'));
        $decoded = base64_decode($base64, true);
        abort_if($decoded === false || ! str_starts_with($decoded, "\x89PNG\r\n\x1a\n"), 422, 'Assinatura invalida.');

        $filename = "assinaturas/{$ticket->id}-{$data['tipo']}-" . Str::random(8) . '.png';
        abort_unless(Storage::disk('local')->put($filename, $decoded), 500, 'Falha ao guardar assinatura.');

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
