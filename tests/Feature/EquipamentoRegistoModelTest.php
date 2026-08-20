<?php

use App\Enums\EquipamentoRegistoTipo;
use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Enums\UserRole;
use App\Models\Cliente;
use App\Models\EquipamentoRegisto;
use App\Models\Ticket;
use App\Models\User;

test('ticket pode ter registo de entrega e devolucao', function () {
    $clienteUser = User::factory()->create(['role' => UserRole::Cliente]);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Teste',
        'email' => $clienteUser->email,
        'telefone' => '912345678',
    ]);
    $ticket = Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Computador nao arranca.',
    ]);
    $tecnico = User::factory()->create(['role' => UserRole::Tecnico]);

    $entrega = EquipamentoRegisto::create([
        'ticket_id' => $ticket->id,
        'tipo' => EquipamentoRegistoTipo::Entrega,
        'user_id' => $tecnico->id,
        'nome_assinante' => 'Cliente Teste',
        'assinatura_path' => 'assinaturas/1-entrega.png',
    ]);
    $devolucao = EquipamentoRegisto::create([
        'ticket_id' => $ticket->id,
        'tipo' => EquipamentoRegistoTipo::Devolucao,
        'user_id' => $tecnico->id,
        'nome_assinante' => 'Cliente Teste',
        'assinatura_path' => 'assinaturas/1-devolucao.png',
    ]);

    expect($ticket->equipamentoRegistos()->count())->toBe(2);
    expect($entrega->tipo)->toBe(EquipamentoRegistoTipo::Entrega);
    expect($devolucao->tipo)->toBe(EquipamentoRegistoTipo::Devolucao);
});
