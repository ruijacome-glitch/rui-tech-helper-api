<?php
// tests/Feature/PagamentoEndpointTest.php
use App\Enums\PagamentoEstado;
use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Enums\UserRole;
use App\Models\Cliente;
use App\Models\Orcamento;
use App\Models\Pagamento;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function criarOrcamentoAprovadoComPagamento(): array
{
    $clienteUser = User::factory()->create(['role' => UserRole::Cliente]);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Teste',
        'email' => $clienteUser->email,
        'telefone' => '912345678',
        'nif' => '123456789',
    ]);
    $ticket = Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Nao arranca.',
    ]);
    $orcamento = Orcamento::create(['ticket_id' => $ticket->id, 'versao' => 1, 'estado' => 'aprovado', 'decided_at' => now()]);
    $pagamento = Pagamento::create(['orcamento_id' => $orcamento->id, 'estado' => PagamentoEstado::Pendente, 'valor' => 45.50]);

    return [$clienteUser, $orcamento, $pagamento];
}

test('cliente escolhe mb e recebe referencia', function () {
    Http::fake([
        'ifthenpay.com/api/multibanco/reference/init' => Http::response([
            'Entidade' => '12345', 'Referencia' => '123456789', 'RequestId' => 'req-1',
        ], 200),
    ]);
    [$clienteUser, $orcamento] = criarOrcamentoAprovadoComPagamento();

    $response = $this->actingAs($clienteUser)->postJson("/api/cliente/orcamentos/{$orcamento->id}/pagamento", [
        'metodo' => 'mb',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('pagamento.entidade', '12345');
    $response->assertJsonPath('pagamento.referencia', '123456789');
});

test('cliente escolhe mbway sem telefone recebe 422', function () {
    [$clienteUser, $orcamento] = criarOrcamentoAprovadoComPagamento();

    $response = $this->actingAs($clienteUser)->postJson("/api/cliente/orcamentos/{$orcamento->id}/pagamento", [
        'metodo' => 'mbway',
    ]);

    $response->assertStatus(422);
});

test('pedido repetido para pagamento pendente nao expirado devolve o existente sem chamar ifthenpay', function () {
    Http::fake();
    [$clienteUser, $orcamento, $pagamento] = criarOrcamentoAprovadoComPagamento();
    $pagamento->update(['metodo' => 'mb', 'entidade' => '12345', 'referencia' => '999', 'expires_at' => now()->addHours(48)]);

    $response = $this->actingAs($clienteUser)->postJson("/api/cliente/orcamentos/{$orcamento->id}/pagamento", [
        'metodo' => 'mb',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('pagamento.referencia', '999');
    Http::assertNothingSent();
});

test('cliente de outro orcamento nao pode gerar pagamento', function () {
    [, $orcamento] = criarOrcamentoAprovadoComPagamento();
    $outroClienteUser = User::factory()->create(['role' => UserRole::Cliente]);
    Cliente::create(['user_id' => $outroClienteUser->id, 'nome' => 'Outro', 'email' => 'outro@example.com', 'telefone' => '913456789']);

    $response = $this->actingAs($outroClienteUser)->postJson("/api/cliente/orcamentos/{$orcamento->id}/pagamento", [
        'metodo' => 'mb',
    ]);

    $response->assertStatus(403);
});
