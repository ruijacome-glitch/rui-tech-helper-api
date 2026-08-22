<?php
// app/Http/Controllers/Tickets/OrcamentoController.php
namespace App\Http\Controllers\Tickets;

use App\Enums\PagamentoEstado;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Mail\OrcamentoPronto;
use App\Models\Orcamento;
use App\Models\Pagamento;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class OrcamentoController extends Controller
{
    public function store(Request $request, Ticket $ticket)
    {
        if ($request->user()->role === UserRole::Tecnico) {
            abort_if($ticket->tecnico_id !== $request->user()->id, 403);
        }

        $data = $request->validate([
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.descricao' => ['required', 'string', 'max:255'],
            'itens.*.quantidade' => ['required', 'integer', 'min:1'],
            'itens.*.preco_unitario' => ['required', 'numeric', 'min:0'],
        ]);

        $orcamento = Orcamento::create([
            'ticket_id' => $ticket->id,
            'versao' => Orcamento::proximaVersao($ticket),
            'estado' => 'pendente',
        ]);

        foreach ($data['itens'] as $item) {
            $orcamento->itens()->create($item);
        }

        $orcamento->load('itens');

        if ($ticket->cliente->email) {
            Mail::to($ticket->cliente->email)->send(new OrcamentoPronto($orcamento));
        }

        return response()->json(['orcamento' => $orcamento], 201);
    }

    public function decisao(Request $request, Orcamento $orcamento)
    {
        $cliente = $request->user()->cliente;
        abort_if($cliente === null || $orcamento->ticket->cliente_id !== $cliente->id, 403);

        $data = $request->validate([
            'decisao' => ['required', 'in:aprovado,rejeitado'],
        ]);

        if ($data['decisao'] === 'aprovado') {
            abort_if(empty($cliente->nif), 422, 'Complete o NIF no seu perfil antes de aceitar o orcamento.');
        }

        $orcamento = DB::transaction(function () use ($orcamento, $data) {
            $locked = Orcamento::whereKey($orcamento->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->estado->value !== 'pendente', 409, 'Orcamento ja foi decidido.');

            $locked->update([
                'estado' => $data['decisao'],
                'decided_at' => now(),
            ]);

            if ($data['decisao'] === 'aprovado') {
                Pagamento::create([
                    'orcamento_id' => $locked->id,
                    'estado' => PagamentoEstado::Pendente,
                    'valor' => $locked->fresh('itens')->total(),
                ]);
            }

            return $locked;
        });

        return response()->json(['orcamento' => $orcamento->fresh()]);
    }

    public function indexAdmin(Request $request)
    {
        $data = $request->validate([
            'estado' => ['nullable', Rule::enum(\App\Enums\OrcamentoEstado::class)],
        ]);

        $orcamentos = Orcamento::query()
            ->with(['itens', 'ticket:id,titulo,cliente_id'])
            ->when($data['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $orcamentos->getCollection()->map(fn (Orcamento $o) => [
                'id' => $o->id,
                'versao' => $o->versao,
                'estado' => $o->estado->value,
                'created_at' => $o->created_at,
                'ticket' => [
                    'id' => $o->ticket->id,
                    'titulo' => $o->ticket->titulo,
                ],
                'itens' => $o->itens->map(fn ($item) => [
                    'descricao' => $item->descricao,
                    'quantidade' => $item->quantidade,
                    'preco_unitario' => $item->preco_unitario,
                ]),
            ])->all(),
            'meta' => [
                'current_page' => $orcamentos->currentPage(),
                'last_page' => $orcamentos->lastPage(),
                'total' => $orcamentos->total(),
            ],
        ]);
    }
}
