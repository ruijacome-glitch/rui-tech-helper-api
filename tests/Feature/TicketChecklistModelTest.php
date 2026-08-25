<?php

use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\User;

function criarTicketParaChecklist(): Ticket
{
    $clienteUser = User::factory()->create(['role' => 'cliente']);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Checklist',
        'email' => $clienteUser->email,
        'telefone' => '912345678',
    ]);

    return Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Nao arranca.',
    ]);
}

test('ticket tem relacao checklistRespostas', function () {
    $ticket = criarTicketParaChecklist();
    $user = User::factory()->create(['role' => 'admin']);

    $ticket->checklistRespostas()->create([
        'item_chave' => 'testar-fonte-alimentacao',
        'concluido' => true,
        'concluido_por_user_id' => $user->id,
        'concluido_at' => now(),
    ]);

    expect($ticket->checklistRespostas)->toHaveCount(1);
    expect($ticket->checklistRespostas->first()->item_chave)->toBe('testar-fonte-alimentacao');
});

test('item_chave e ticket_id sao unicos em conjunto', function () {
    $ticket = criarTicketParaChecklist();

    $ticket->checklistRespostas()->create(['item_chave' => 'testar-fonte-alimentacao']);

    expect(fn () => $ticket->checklistRespostas()->create(['item_chave' => 'testar-fonte-alimentacao']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
