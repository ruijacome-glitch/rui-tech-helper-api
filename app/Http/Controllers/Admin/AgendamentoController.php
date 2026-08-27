<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agendamento;
use Illuminate\Http\Request;

class AgendamentoController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'mes' => ['nullable', 'integer', 'between:1,12'],
            'ano' => ['nullable', 'integer', 'min:2020'],
        ]);

        $agendamentos = Agendamento::query()
            ->with(['cliente:id,nome', 'tecnico:id,name', 'ticket:id,titulo'])
            ->when($data['ano'] ?? null, fn ($q, $ano) => $q->whereYear('data_hora', $ano))
            ->when($data['mes'] ?? null, fn ($q, $mes) => $q->whereMonth('data_hora', $mes))
            ->orderBy('data_hora')
            ->get();

        return response()->json([
            'data' => $agendamentos->map(fn (Agendamento $a) => $this->present($a)),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cliente_id' => ['required', 'exists:clientes,id'],
            'tecnico_id' => ['nullable', 'exists:users,id'],
            'ticket_id' => ['nullable', 'exists:tickets,id'],
            'data_hora' => ['required', 'date'],
            'morada' => ['nullable', 'string', 'max:255'],
            'notas' => ['nullable', 'string'],
        ]);

        $agendamento = Agendamento::create($data + ['estado' => 'marcado']);
        $agendamento->load(['cliente:id,nome', 'tecnico:id,name', 'ticket:id,titulo']);

        return response()->json(['agendamento' => $this->present($agendamento)], 201);
    }

    public function update(Request $request, Agendamento $agendamento)
    {
        $data = $request->validate([
            'tecnico_id' => ['nullable', 'exists:users,id'],
            'data_hora' => ['sometimes', 'date'],
            'morada' => ['nullable', 'string', 'max:255'],
            'notas' => ['nullable', 'string'],
            'estado' => ['sometimes', 'in:marcado,confirmado,concluido,cancelado'],
        ]);

        $agendamento->update($data);
        $agendamento->load(['cliente:id,nome', 'tecnico:id,name', 'ticket:id,titulo']);

        return response()->json(['agendamento' => $this->present($agendamento)]);
    }

    public function destroy(Agendamento $agendamento)
    {
        $agendamento->delete();

        return response()->json(['ok' => true]);
    }

    private function present(Agendamento $a): array
    {
        return [
            'id' => $a->id,
            'cliente_id' => $a->cliente_id,
            'cliente_nome' => $a->cliente?->nome,
            'tecnico_id' => $a->tecnico_id,
            'tecnico_nome' => $a->tecnico?->name,
            'ticket_id' => $a->ticket_id,
            'ticket_titulo' => $a->ticket?->titulo,
            'data_hora' => $a->data_hora,
            'morada' => $a->morada,
            'notas' => $a->notas,
            'estado' => $a->estado->value,
        ];
    }
}
