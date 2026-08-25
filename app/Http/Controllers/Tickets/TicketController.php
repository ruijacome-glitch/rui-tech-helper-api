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
            'estado' => ['required', 'in:recebido,em_diagnostico,em_reparacao,pronto_levantamento,aguarda_pecas,reparacao_concluida,entregue,cancelado'],
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

    public function indexAdmin(Request $request)
    {
        $data = $request->validate([
            'estado' => ['nullable', Rule::enum(\App\Enums\TicketEstado::class)],
            'categoria' => ['nullable', Rule::enum(\App\Enums\TicketCategoria::class)],
            'prioridade' => ['nullable', Rule::enum(\App\Enums\TicketPrioridade::class)],
            'tecnico_id' => ['nullable', Rule::exists('users', 'id')->where('role', UserRole::Tecnico->value)],
        ]);

        $tickets = Ticket::query()
            ->when($data['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->when($data['categoria'] ?? null, fn ($q, $v) => $q->where('categoria', $v))
            ->when($data['prioridade'] ?? null, fn ($q, $v) => $q->where('prioridade', $v))
            ->when($data['tecnico_id'] ?? null, fn ($q, $v) => $q->where('tecnico_id', $v))
            ->latest()
            ->paginate(20);

        return response()->json($this->serializeTicketPage($tickets));
    }

    public function indexTecnico(Request $request)
    {
        $data = $request->validate([
            'estado' => ['nullable', Rule::enum(\App\Enums\TicketEstado::class)],
            'categoria' => ['nullable', Rule::enum(\App\Enums\TicketCategoria::class)],
            'prioridade' => ['nullable', Rule::enum(\App\Enums\TicketPrioridade::class)],
        ]);

        $tickets = Ticket::query()
            ->where('tecnico_id', $request->user()->id)
            ->when($data['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->when($data['categoria'] ?? null, fn ($q, $v) => $q->where('categoria', $v))
            ->when($data['prioridade'] ?? null, fn ($q, $v) => $q->where('prioridade', $v))
            ->latest()
            ->paginate(20);

        return response()->json($this->serializeTicketPage($tickets));
    }

    public function showAdmin(Ticket $ticket)
    {
        return response()->json(['ticket' => $this->serializeTicketDetail($ticket)]);
    }

    public function showTecnico(Request $request, Ticket $ticket)
    {
        abort_if($ticket->tecnico_id !== $request->user()->id, 403);

        return response()->json(['ticket' => $this->serializeTicketDetail($ticket)]);
    }

    public function atribuir(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'tecnico_id' => ['required', Rule::exists('users', 'id')->where('role', UserRole::Tecnico->value)],
        ]);

        $ticket->update(['tecnico_id' => $data['tecnico_id']]);

        return response()->json(['ticket' => $ticket->fresh(['tecnico'])]);
    }

    private function serializeTicketDetail(Ticket $ticket): array
    {
        $ticket->load([
            'cliente',
            'tecnico',
            'orcamentos.itens',
            'orcamentos.pagamento',
            'issues.resolvidoPor',
            'checklistRespostas.concluidoPor',
        ]);

        $checklist = $this->buildChecklist($ticket, comIdentidade: true);

        return [
            'id' => $ticket->id,
            'titulo' => $ticket->titulo,
            'descricao' => $ticket->descricao,
            'estado' => $ticket->estado->value,
            'categoria' => $ticket->categoria->value,
            'prioridade' => $ticket->prioridade->value,
            'created_at' => $ticket->created_at,
            'tracking_token' => $ticket->tracking_token,
            'cliente' => [
                'id' => $ticket->cliente->id,
                'nome' => $ticket->cliente->nome,
                'email' => $ticket->cliente->email,
                'telefone' => $ticket->cliente->telefone,
            ],
            'tecnico' => $ticket->tecnico ? [
                'id' => $ticket->tecnico->id,
                'name' => $ticket->tecnico->name,
            ] : null,
            'eventos' => $ticket->eventos()->orderByDesc('created_at')->get()->map(fn ($evento) => [
                'estado_anterior' => $evento->estado_anterior->value,
                'estado_novo' => $evento->estado_novo->value,
                'observacao' => $evento->observacao,
                'created_at' => $evento->created_at,
            ]),
            'anexos' => $ticket->anexos()->orderBy('created_at')->get()->map(fn ($anexo) => [
                'id' => $anexo->id,
                'nome_original' => $anexo->nome_original,
                'content_type' => $anexo->content_type,
                'size' => $anexo->size,
                'created_at' => $anexo->created_at,
            ]),
            'orcamentos' => $ticket->orcamentos->map(fn (\App\Models\Orcamento $orcamento) => [
                'id' => $orcamento->id,
                'versao' => $orcamento->versao,
                'estado' => $orcamento->estado->value,
                'created_at' => $orcamento->created_at,
                'decided_at' => $orcamento->decided_at,
                'itens' => $orcamento->itens->map(fn ($item) => [
                    'descricao' => $item->descricao,
                    'quantidade' => $item->quantidade,
                    'preco_unitario' => $item->preco_unitario,
                ]),
                'pagamento' => $orcamento->pagamento ? [
                    'id' => $orcamento->pagamento->id,
                    'estado' => $orcamento->pagamento->estado->value,
                    'valor' => $orcamento->pagamento->valor,
                ] : null,
            ]),
            'issues' => $ticket->issues->map(fn (\App\Models\TicketIssue $issue) => [
                'id' => $issue->id,
                'descricao' => $issue->descricao,
                'resultado' => $issue->resultado,
                'observacao' => $issue->observacao,
                'resolvido_por' => $issue->resolvidoPor?->name,
                'resolvido_at' => $issue->resolvido_at,
            ]),
            'checklist' => $checklist,
        ];
    }

    private function buildChecklist(Ticket $ticket, bool $comIdentidade): \Illuminate\Support\Collection
    {
        $itensCategoria = config('checklists')[$ticket->categoria->value] ?? [];

        // Usar a colecao ja carregada quando a relacao foi eager-loaded (caso staff);
        // caso contrario, consultar sem cachear na relacao para nao "vazar" a relacao
        // crua para dentro de $ticket->toArray() (usado pelo serializer de cliente/publico).
        $respostas = ($ticket->relationLoaded('checklistRespostas')
            ? $ticket->checklistRespostas
            : $ticket->checklistRespostas()->get())->keyBy('item_chave');

        return collect($itensCategoria)->map(function ($label, $itemChave) use ($respostas, $comIdentidade) {
            $resposta = $respostas->get($itemChave);

            $item = [
                'item_chave' => $itemChave,
                'label' => $label,
                'concluido' => $resposta?->concluido ?? false,
            ];

            if ($comIdentidade) {
                $item['concluido_por'] = $resposta?->concluidoPor?->name;
            }

            $item['concluido_at'] = $resposta?->concluido_at;

            return $item;
        })->values();
    }

    private function serializeTicketPage(\Illuminate\Pagination\LengthAwarePaginator $tickets): array
    {
        return [
            'data' => $tickets->getCollection()->map(fn (Ticket $t) => [
                'id' => $t->id,
                'titulo' => $t->titulo,
                'estado' => $t->estado->value,
                'categoria' => $t->categoria->value,
                'prioridade' => $t->prioridade->value,
                'cliente_id' => $t->cliente_id,
                'tecnico_id' => $t->tecnico_id,
                'created_at' => $t->created_at,
            ])->all(),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'total' => $tickets->total(),
            ],
        ];
    }

    public function show(Request $request, Ticket $ticket)
    {
        $cliente = $request->user()->cliente;
        abort_if($cliente === null || $ticket->cliente_id !== $cliente->id, 403);

        return response()->json(['ticket' => $this->serializeTicketDetailCliente($ticket)]);
    }

    public function serializeTicketDetailCliente(Ticket $ticket): array
    {
        $eventos = $ticket->eventos()->orderBy('created_at')->get()->map(fn ($evento) => [
            'estado_anterior' => $evento->estado_anterior->value,
            'estado_novo' => $evento->estado_novo->value,
            'observacao' => $evento->observacao_visivel_cliente ? $evento->observacao : null,
            'created_at' => $evento->created_at,
        ]);

        $anexos = $ticket->anexos()->orderBy('created_at')->get()->map(fn ($anexo) => [
            'id' => $anexo->id,
            'nome_original' => $anexo->nome_original,
            'content_type' => $anexo->content_type,
            'size' => $anexo->size,
            'created_at' => $anexo->created_at,
        ]);

        $orcamentos = $ticket->orcamentos()->with(['itens', 'pagamento'])->orderBy('versao')->get()->map(fn ($orcamento) => [
            'id' => $orcamento->id,
            'versao' => $orcamento->versao,
            'estado' => $orcamento->estado->value,
            'created_at' => $orcamento->created_at,
            'decided_at' => $orcamento->decided_at,
            'itens' => $orcamento->itens->map(fn ($item) => [
                'descricao' => $item->descricao,
                'quantidade' => $item->quantidade,
                'preco_unitario' => $item->preco_unitario,
            ]),
            'total' => $orcamento->total(),
            'pagamento' => $orcamento->pagamento ? [
                'id' => $orcamento->pagamento->id,
                'estado' => $orcamento->pagamento->estado->value,
                'valor' => $orcamento->pagamento->valor,
            ] : null,
        ]);

        $issues = $ticket->issues()->orderBy('created_at')->get()->map(fn ($issue) => [
            'id' => $issue->id,
            'descricao' => $issue->descricao,
            'resultado' => $issue->resultado,
            'observacao' => $issue->observacao,
            'resolvido_at' => $issue->resolvido_at,
        ]);

        $checklist = $this->buildChecklist($ticket, comIdentidade: false);

        $ticketArray = $ticket->toArray();
        unset($ticketArray['tecnico_id']);
        $ticketArray['eventos'] = $eventos;
        $ticketArray['anexos'] = $anexos;
        $ticketArray['orcamentos'] = $orcamentos;
        $ticketArray['issues'] = $issues;
        $ticketArray['checklist'] = $checklist;

        return $ticketArray;
    }
}
