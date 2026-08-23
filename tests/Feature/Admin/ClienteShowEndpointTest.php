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

function criarClienteComTicketsParaShow(): Cliente
{
    return Cliente::create([
        'nome' => 'Carla Dias',
        'email' => 'carla@example.com',
        'telefone' => '911000003',
        'morada' => 'Rua A, 1',
        'nif' => '123456789',
    ]);
}

test('admin ve detalhe de cliente com resumo, intervencoes e orcamentos', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $cliente = criarClienteComTicketsParaShow();

    $ticket = Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Resolvido,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'Ticket A',
        'descricao' => 'Descricao.',
    ]);

    $orcamento = Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => 'aprovado', 'decided_at' => now()]);
    Pagamento::create(['orcamento_id' => $orcamento->id, 'estado' => 'pago', 'valor' => 75.00, 'paid_at' => now()]);

    $response = $this->actingAs($admin)->getJson("/api/admin/clientes/{$cliente->id}");

    $response->assertOk();
    $response->assertJsonPath('cliente.nome', 'Carla Dias');
    $response->assertJsonPath('resumo.intervencoes_total', 1);
    $response->assertJsonPath('resumo.faturacao_total', '75.00');
    $response->assertJsonCount(1, 'intervencoes');
    $response->assertJsonCount(1, 'orcamentos');
});

test('detalhe de cliente inexistente devolve 404', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->getJson('/api/admin/clientes/999999');

    $response->assertStatus(404);
});

test('tecnico nao pode ver detalhe de cliente admin', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $cliente = criarClienteComTicketsParaShow();

    $response = $this->actingAs($tecnico)->getJson("/api/admin/clientes/{$cliente->id}");

    $response->assertStatus(403);
});
