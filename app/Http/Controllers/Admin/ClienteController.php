<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ConviteCliente;
use App\Enums\PagamentoEstado;
use App\Models\Cliente;
use App\Models\Convite;
use App\Models\Orcamento;
use App\Models\Pagamento;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $clientes = Cliente::query()
            ->withCount('tickets')
            ->when($data['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nome', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('telefone', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $clientes->getCollection()->map(fn (Cliente $cliente) => [
                'id' => $cliente->id,
                'nome' => $cliente->nome,
                'email' => $cliente->email,
                'telefone' => $cliente->telefone,
                'created_at' => $cliente->created_at,
                'intervencoes_count' => $cliente->tickets_count,
            ]),
            'meta' => [
                'current_page' => $clientes->currentPage(),
                'last_page' => $clientes->lastPage(),
                'total' => $clientes->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $cliente = Cliente::create($data);

        $plaintextToken = Str::random(64);
        $convite = Convite::create([
            'cliente_id' => $cliente->id,
            'token_hash' => hash('sha256', $plaintextToken),
            'expires_at' => now()->addDays(7),
        ]);

        if ($cliente->email) {
            try {
                Mail::to($cliente->email)->send(new ConviteCliente($convite, $plaintextToken));
            } catch (\Throwable $e) {
                Log::error('Falha a enviar email de convite', [
                    'cliente_id' => $cliente->id,
                    'erro' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['cliente' => $cliente], 201);
    }

    public function reenviarConvite(Cliente $cliente)
    {
        abort_if(! $cliente->email, 422, 'Cliente não tem email definido.');

        Convite::where('cliente_id', $cliente->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $plaintextToken = Str::random(64);
        $convite = Convite::create([
            'cliente_id' => $cliente->id,
            'token_hash' => hash('sha256', $plaintextToken),
            'expires_at' => now()->addDays(7),
        ]);

        try {
            Mail::to($cliente->email)->send(new ConviteCliente($convite, $plaintextToken));
        } catch (\Throwable $e) {
            Log::error('Falha a enviar email de convite', [
                'cliente_id' => $cliente->id,
                'erro' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Convite criado mas envio de email falhou.'], 502);
        }

        return response()->json(['message' => 'Convite reenviado.']);
    }

    public function show(Cliente $cliente)
    {
        $cliente->load(['tickets' => fn ($q) => $q->latest()->limit(20)]);
        $cliente->loadCount('tickets');

        $faturacaoTotal = Pagamento::query()
            ->where('estado', PagamentoEstado::Pago)
            ->whereHas('orcamento.ticket', fn ($q) => $q->where('cliente_id', $cliente->id))
            ->sum('valor');

        $orcamentos = Orcamento::query()
            ->whereHas('ticket', fn ($q) => $q->where('cliente_id', $cliente->id))
            ->with('itens')
            ->latest('created_at')
            ->limit(20)
            ->get();

        return response()->json([
            'cliente' => [
                'id' => $cliente->id,
                'nome' => $cliente->nome,
                'email' => $cliente->email,
                'telefone' => $cliente->telefone,
                'morada' => $cliente->morada,
                'nif' => $cliente->nif,
                'notas' => $cliente->notas,
                'created_at' => $cliente->created_at,
            ],
            'resumo' => [
                'intervencoes_total' => $cliente->tickets_count,
                'faturacao_total' => number_format((float) $faturacaoTotal, 2, '.', ''),
                'ultima_intervencao_em' => $cliente->tickets->max('created_at'),
            ],
            'intervencoes' => $cliente->tickets->map(fn (Ticket $t) => [
                'id' => $t->id,
                'titulo' => $t->titulo,
                'estado' => $t->estado->value,
                'categoria' => $t->categoria->value,
                'prioridade' => $t->prioridade->value,
                'created_at' => $t->created_at,
            ]),
            'orcamentos' => $orcamentos->map(fn (Orcamento $o) => [
                'id' => $o->id,
                'ticket_id' => $o->ticket_id,
                'valor_total' => number_format($o->total(), 2, '.', ''),
                'estado' => $o->estado->value,
                'created_at' => $o->created_at,
            ]),
        ]);
    }
}
