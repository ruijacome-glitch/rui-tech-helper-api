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

function criarTicketParaPagamentoIndex(): Ticket
{
    $clienteUser = User::factory()->create(['role' => 'cliente']);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Pag',
        'email' => 'cliente-pag@example.com',
        'telefone' => '914444444',
    ]);

    return Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'Ticket com pagamento',
        'descricao' => 'Descricao.',
    ]);
}

test('admin lista pagamentos com orcamento e ticket', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = criarTicketParaPagamentoIndex();
    $orcamento = Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => 'aprovado', 'decided_at' => now()]);
    Pagamento::create(['orcamento_id' => $orcamento->id, 'estado' => 'pendente', 'valor' => 45.50]);

    $response = $this->actingAs($admin)->getJson('/api/admin/pagamentos');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.orcamento.ticket.id', $ticket->id);
});

test('admin filtra pagamentos por estado', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = criarTicketParaPagamentoIndex();
    $orcamento1 = Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => 'aprovado', 'decided_at' => now()]);
    $orcamento2 = Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 2, 'estado' => 'aprovado', 'decided_at' => now()]);
    Pagamento::create(['orcamento_id' => $orcamento1->id, 'estado' => 'pendente', 'valor' => 10]);
    Pagamento::create(['orcamento_id' => $orcamento2->id, 'estado' => 'pago', 'valor' => 20, 'paid_at' => now()]);

    $response = $this->actingAs($admin)->getJson('/api/admin/pagamentos?estado=pago');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.estado', 'pago');
});

test('tecnico nao pode listar pagamentos admin', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);

    $response = $this->actingAs($tecnico)->getJson('/api/admin/pagamentos');

    $response->assertStatus(403);
});
