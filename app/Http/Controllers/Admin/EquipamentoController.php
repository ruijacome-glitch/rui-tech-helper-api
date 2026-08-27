<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Equipamento;
use Illuminate\Http\Request;

class EquipamentoController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'cliente_id' => ['nullable', 'exists:clientes,id'],
        ]);

        $equipamentos = Equipamento::query()
            ->with('cliente:id,nome')
            ->withCount('tickets')
            ->when($data['cliente_id'] ?? null, fn ($q, $id) => $q->where('cliente_id', $id))
            ->when($data['search'] ?? null, function ($q, $search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('marca', 'like', "%{$search}%")
                        ->orWhere('modelo', 'like', "%{$search}%")
                        ->orWhere('numero_serie', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $equipamentos->getCollection()->map(fn (Equipamento $e) => $this->present($e)),
            'meta' => [
                'current_page' => $equipamentos->currentPage(),
                'last_page' => $equipamentos->lastPage(),
                'total' => $equipamentos->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cliente_id' => ['required', 'exists:clientes,id'],
            'tipo' => ['required', 'string', 'max:50'],
            'marca' => ['nullable', 'string', 'max:255'],
            'modelo' => ['nullable', 'string', 'max:255'],
            'numero_serie' => ['nullable', 'string', 'max:255'],
            'notas' => ['nullable', 'string'],
        ]);

        $equipamento = Equipamento::create($data);
        $equipamento->load('cliente:id,nome');

        return response()->json(['equipamento' => $this->present($equipamento)], 201);
    }

    public function show(Equipamento $equipamento)
    {
        $equipamento->load(['cliente:id,nome', 'tickets' => fn ($q) => $q->latest()->limit(20)]);

        return response()->json([
            'equipamento' => $this->present($equipamento),
            'tickets' => $equipamento->tickets->map(fn ($t) => [
                'id' => $t->id,
                'titulo' => $t->titulo,
                'estado' => $t->estado->value,
                'created_at' => $t->created_at,
            ]),
        ]);
    }

    public function update(Request $request, Equipamento $equipamento)
    {
        $data = $request->validate([
            'tipo' => ['sometimes', 'string', 'max:50'],
            'marca' => ['nullable', 'string', 'max:255'],
            'modelo' => ['nullable', 'string', 'max:255'],
            'numero_serie' => ['nullable', 'string', 'max:255'],
            'notas' => ['nullable', 'string'],
        ]);

        $equipamento->update($data);
        $equipamento->load('cliente:id,nome');

        return response()->json(['equipamento' => $this->present($equipamento)]);
    }

    private function present(Equipamento $e): array
    {
        return [
            'id' => $e->id,
            'cliente_id' => $e->cliente_id,
            'cliente_nome' => $e->cliente?->nome,
            'tipo' => $e->tipo,
            'marca' => $e->marca,
            'modelo' => $e->modelo,
            'numero_serie' => $e->numero_serie,
            'notas' => $e->notas,
            'tickets_count' => $e->tickets_count ?? null,
            'created_at' => $e->created_at,
        ];
    }
}
