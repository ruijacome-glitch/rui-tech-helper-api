<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MovimentoStock;
use App\Models\Peca;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PecaController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'stock_baixo' => ['nullable', 'boolean'],
        ]);

        $pecas = Peca::query()
            ->when($data['search'] ?? null, fn ($q, $search) => $q->where('nome', 'like', "%{$search}%"))
            ->when($data['stock_baixo'] ?? null, fn ($q) => $q->whereColumn('quantidade_atual', '<=', 'stock_minimo'))
            ->orderBy('nome')
            ->paginate(20);

        return response()->json([
            'data' => $pecas->getCollection()->map(fn (Peca $p) => $this->present($p)),
            'meta' => [
                'current_page' => $pecas->currentPage(),
                'last_page' => $pecas->lastPage(),
                'total' => $pecas->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'preco_custo' => ['required', 'numeric', 'min:0'],
            'preco_venda' => ['required', 'numeric', 'min:0'],
            'quantidade_atual' => ['nullable', 'integer', 'min:0'],
            'stock_minimo' => ['nullable', 'integer', 'min:0'],
        ]);

        $peca = Peca::create($data);

        return response()->json(['peca' => $this->present($peca)], 201);
    }

    public function update(Request $request, Peca $peca)
    {
        $data = $request->validate([
            'nome' => ['sometimes', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'preco_custo' => ['sometimes', 'numeric', 'min:0'],
            'preco_venda' => ['sometimes', 'numeric', 'min:0'],
            'stock_minimo' => ['sometimes', 'integer', 'min:0'],
        ]);

        $peca->update($data);

        return response()->json(['peca' => $this->present($peca)]);
    }

    public function movimentar(Request $request, Peca $peca)
    {
        $data = $request->validate([
            'tipo' => ['required', 'in:entrada,saida,ajuste'],
            'quantidade' => ['required', 'integer', 'min:1'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $peca = DB::transaction(function () use ($peca, $data, $request) {
            $delta = match ($data['tipo']) {
                'entrada' => $data['quantidade'],
                'saida' => -$data['quantidade'],
                'ajuste' => $data['quantidade'] - $peca->quantidade_atual,
            };

            $peca->increment('quantidade_atual', $delta);

            MovimentoStock::create([
                'peca_id' => $peca->id,
                'tipo' => $data['tipo'],
                'quantidade' => $data['quantidade'],
                'motivo' => $data['motivo'] ?? null,
                'user_id' => $request->user()?->id,
            ]);

            return $peca->fresh();
        });

        return response()->json(['peca' => $this->present($peca)]);
    }

    private function present(Peca $p): array
    {
        return [
            'id' => $p->id,
            'nome' => $p->nome,
            'descricao' => $p->descricao,
            'preco_custo' => $p->preco_custo,
            'preco_venda' => $p->preco_venda,
            'quantidade_atual' => $p->quantidade_atual,
            'stock_minimo' => $p->stock_minimo,
            'stock_baixo' => $p->stockBaixo(),
        ];
    }
}
