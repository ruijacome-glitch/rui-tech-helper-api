<?php

use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\TicketIssue;
use App\Models\User;

function criarTicketParaIssue(): Ticket
{
    $clienteUser = User::factory()->create(['role' => 'cliente']);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Issue',
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

test('ticket tem issues relacionadas, resultado por omissao pendente', function () {
    $ticket = criarTicketParaIssue();

    $issue = $ticket->issues()->create(['descricao' => 'Fonte de alimentacao morta']);

    expect($issue->resultado)->toBe('pendente');
    expect($ticket->issues)->toHaveCount(1);
});

test('issue guarda quem resolveu e quando', function () {
    $ticket = criarTicketParaIssue();
    $tecnico = User::factory()->create(['role' => 'tecnico']);

    $issue = $ticket->issues()->create(['descricao' => 'Fonte de alimentacao morta']);
    $issue->update([
        'resultado' => 'resolvido',
        'resolvido_por_user_id' => $tecnico->id,
        'resolvido_at' => now(),
    ]);

    expect($issue->fresh()->resultado)->toBe('resolvido');
    expect($issue->fresh()->resolvido_por_user_id)->toBe($tecnico->id);
});
