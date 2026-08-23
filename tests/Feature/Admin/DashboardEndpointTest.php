<?php

use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Orcamento;
use App\Models\Pagamento;
use App\Models\Ticket;
use App\Models\User;

function criarTicketParaDashboard(Cliente $cliente, TicketEstado $estado): Ticket
{
    return Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => $estado,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'Ticket dashboard',
        'descricao' => 'Descricao.',
    ]);
}

test('admin ve kpis do dashboard', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $cliente = Cliente::create(['nome' => 'Dashboard Cliente', 'email' => 'dash@example.com', 'telefone' => '911000004']);

    $abertoTicket = criarTicketParaDashboard($cliente, TicketEstado::Aberto);
    criarTicketParaDashboard($cliente, TicketEstado::Resolvido);
    criarTicketParaDashboard($cliente, TicketEstado::Cancelado);

    $orcamento = Orcamento::create(['ticket_id' => $abertoTicket->id, 'versao' => 1, 'estado' => 'aprovado', 'decided_at' => now()]);
    Pagamento::create(['orcamento_id' => $orcamento->id, 'estado' => 'pago', 'valor' => 100.00, 'paid_at' => now()]);

    $response = $this->actingAs($admin)->getJson('/api/admin/dashboard');

    $response->assertOk();
    $response->assertJsonPath('clientes.total', 1);
    $response->assertJsonPath('intervencoes.total', 3);
    $response->assertJsonPath('pendentes', 1);
    $response->assertJsonPath('faturacao_mes', '100.00');
    $response->assertJsonPath('agendamentos.total', 0);
    $response->assertJsonPath('por_estado.resolvido', 1);
    $response->assertJsonPath('por_estado.cancelado', 1);
    $response->assertJsonCount(3, 'intervencoes_recentes');
});

test('tecnico nao pode ver dashboard admin', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);

    $response = $this->actingAs($tecnico)->getJson('/api/admin/dashboard');

    $response->assertStatus(403);
});
