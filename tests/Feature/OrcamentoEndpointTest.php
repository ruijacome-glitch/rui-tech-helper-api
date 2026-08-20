<?php
// tests/Feature/OrcamentoEndpointTest.php
use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Mail\OrcamentoPronto;
use App\Models\Cliente;
use App\Models\Orcamento;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function criarTicketComTecnicoParaOrcamento(?User $tecnico = null, string $email = 'cliente@example.com'): Ticket
{
    $clienteUser = User::factory()->create(['role' => 'cliente']);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Teste',
        'email' => $email,
        'telefone' => '912345678',
    ]);

    return Ticket::create([
        'cliente_id' => $cliente->id,
        'tecnico_id' => $tecnico?->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Nao arranca.',
    ]);
}

test('tecnico atribuido cria orcamento v1 e email e enviado', function () {
    Mail::fake();
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketComTecnicoParaOrcamento($tecnico);

    $response = $this->actingAs($tecnico)->postJson("/api/tecnico/tickets/{$ticket->id}/orcamentos", [
        'itens' => [
            ['descricao' => 'Fonte', 'quantidade' => 1, 'preco_unitario' => 45.50],
        ],
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('orcamento.versao', 1);
    Mail::assertSent(OrcamentoPronto::class);
});

test('tecnico nao atribuido nao pode criar orcamento', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $outroTecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketComTecnicoParaOrcamento($outroTecnico);

    $response = $this->actingAs($tecnico)->postJson("/api/tecnico/tickets/{$ticket->id}/orcamentos", [
        'itens' => [['descricao' => 'Fonte', 'quantidade' => 1, 'preco_unitario' => 45.50]],
    ]);

    $response->assertStatus(403);
});

test('cliente aprova orcamento', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketComTecnicoParaOrcamento($tecnico);
    $orcamento = Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => 'pendente']);

    $response = $this->actingAs($ticket->cliente->user)->postJson("/api/cliente/orcamentos/{$orcamento->id}/decisao", [
        'decisao' => 'aprovado',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('orcamento.estado', 'aprovado');
});

test('cliente rejeita orcamento e tecnico cria nova versao', function () {
    Mail::fake();
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketComTecnicoParaOrcamento($tecnico);
    $orcamento = Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => 'pendente']);

    $this->actingAs($ticket->cliente->user)->postJson("/api/cliente/orcamentos/{$orcamento->id}/decisao", [
        'decisao' => 'rejeitado',
    ])->assertStatus(200);

    $response = $this->actingAs($tecnico)->postJson("/api/tecnico/tickets/{$ticket->id}/orcamentos", [
        'itens' => [['descricao' => 'Fonte ajustada', 'quantidade' => 1, 'preco_unitario' => 40.00]],
    ]);

    $response->assertJsonPath('orcamento.versao', 2);
});

test('decidir orcamento ja decidido devolve 409', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketComTecnicoParaOrcamento($tecnico);
    $orcamento = Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => 'aprovado', 'decided_at' => now()]);

    $response = $this->actingAs($ticket->cliente->user)->postJson("/api/cliente/orcamentos/{$orcamento->id}/decisao", [
        'decisao' => 'rejeitado',
    ]);

    $response->assertStatus(409);
});

test('cliente de outro ticket nao pode decidir orcamento', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketComTecnicoParaOrcamento($tecnico);
    $orcamento = Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => 'pendente']);
    $outroClienteUser = User::factory()->create(['role' => 'cliente']);
    Cliente::create([
        'user_id' => $outroClienteUser->id,
        'nome' => 'Outro',
        'email' => 'outro@example.com',
        'telefone' => '913456789',
    ]);

    $response = $this->actingAs($outroClienteUser)->postJson("/api/cliente/orcamentos/{$orcamento->id}/decisao", [
        'decisao' => 'aprovado',
    ]);

    $response->assertStatus(403);
});
