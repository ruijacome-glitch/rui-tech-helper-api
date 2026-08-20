<?php
// tests/Feature/EquipamentoRegistoEndpointTest.php
use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

const ASSINATURA_PNG_BASE64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

function criarTicketComTecnicoParaEquip(?User $tecnico = null): Ticket
{
    $clienteUser = User::factory()->create(['role' => 'cliente']);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Teste',
        'email' => $clienteUser->email,
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

test('tecnico regista entrega de equipamento com assinatura', function () {
    Storage::fake('local');
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketComTecnicoParaEquip($tecnico);

    $response = $this->actingAs($tecnico)->postJson("/api/tecnico/tickets/{$ticket->id}/equipamento", [
        'tipo' => 'entrega',
        'nome_assinante' => 'Cliente Teste',
        'assinatura' => ASSINATURA_PNG_BASE64,
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('registo.tipo', 'entrega');
});

test('nao pode registar duas vezes o mesmo tipo para o mesmo ticket', function () {
    Storage::fake('local');
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketComTecnicoParaEquip($tecnico);

    $this->actingAs($tecnico)->postJson("/api/tecnico/tickets/{$ticket->id}/equipamento", [
        'tipo' => 'entrega',
        'nome_assinante' => 'Cliente Teste',
        'assinatura' => ASSINATURA_PNG_BASE64,
    ])->assertStatus(201);

    $response = $this->actingAs($tecnico)->postJson("/api/tecnico/tickets/{$ticket->id}/equipamento", [
        'tipo' => 'entrega',
        'nome_assinante' => 'Cliente Teste',
        'assinatura' => ASSINATURA_PNG_BASE64,
    ]);

    $response->assertStatus(409);
});

test('mesmo ticket pode ter entrega e devolucao', function () {
    Storage::fake('local');
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketComTecnicoParaEquip($tecnico);

    $this->actingAs($tecnico)->postJson("/api/tecnico/tickets/{$ticket->id}/equipamento", [
        'tipo' => 'entrega',
        'nome_assinante' => 'Cliente Teste',
        'assinatura' => ASSINATURA_PNG_BASE64,
    ])->assertStatus(201);

    $response = $this->actingAs($tecnico)->postJson("/api/tecnico/tickets/{$ticket->id}/equipamento", [
        'tipo' => 'devolucao',
        'nome_assinante' => 'Cliente Teste',
        'assinatura' => ASSINATURA_PNG_BASE64,
    ]);

    $response->assertStatus(201);
});

test('tecnico nao atribuido nao pode registar equipamento', function () {
    Storage::fake('local');
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $outroTecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketComTecnicoParaEquip($outroTecnico);

    $response = $this->actingAs($tecnico)->postJson("/api/tecnico/tickets/{$ticket->id}/equipamento", [
        'tipo' => 'entrega',
        'nome_assinante' => 'Cliente Teste',
        'assinatura' => ASSINATURA_PNG_BASE64,
    ]);

    $response->assertStatus(403);
});

test('assinatura com conteudo invalido (nao PNG) e rejeitada', function () {
    Storage::fake('local');
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketComTecnicoParaEquip($tecnico);

    $response = $this->actingAs($tecnico)->postJson("/api/tecnico/tickets/{$ticket->id}/equipamento", [
        'tipo' => 'entrega',
        'nome_assinante' => 'Cliente Teste',
        'assinatura' => 'data:image/png;base64,' . base64_encode('not a png'),
    ]);

    $response->assertStatus(422);
});

test('assinatura sem o prefixo obrigatorio e rejeitada', function () {
    Storage::fake('local');
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketComTecnicoParaEquip($tecnico);

    $response = $this->actingAs($tecnico)->postJson("/api/tecnico/tickets/{$ticket->id}/equipamento", [
        'tipo' => 'entrega',
        'nome_assinante' => 'Cliente Teste',
        'assinatura' => 'nao-e-um-data-uri-valido',
    ]);

    $response->assertStatus(422);
});
