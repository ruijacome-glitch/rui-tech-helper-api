<?php
// tests/Feature/AnexoEndpointTest.php
use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function criarTicketComTecnicoParaAnexo(?User $tecnico = null): Ticket
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

test('tecnico faz upload de anexo', function () {
    Storage::fake('local');
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketComTecnicoParaAnexo($tecnico);
    $file = UploadedFile::fake()->image('foto.jpg')->size(100);

    $response = $this->actingAs($tecnico)->postJson("/api/tecnico/tickets/{$ticket->id}/anexos", [
        'ficheiro' => $file,
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('anexo.nome_original', 'foto.jpg');
});

test('ficheiro demasiado grande devolve 422', function () {
    Storage::fake('local');
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketComTecnicoParaAnexo($tecnico);
    $file = UploadedFile::fake()->create('doc.pdf', 20000, 'application/pdf');

    $response = $this->actingAs($tecnico)->postJson("/api/tecnico/tickets/{$ticket->id}/anexos", [
        'ficheiro' => $file,
    ]);

    $response->assertStatus(422);
});

test('tecnico nao atribuido nao pode fazer upload', function () {
    Storage::fake('local');
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $outroTecnico = User::factory()->create(['role' => 'tecnico']);
    $ticket = criarTicketComTecnicoParaAnexo($outroTecnico);
    $file = UploadedFile::fake()->image('foto.jpg');

    $response = $this->actingAs($tecnico)->postJson("/api/tecnico/tickets/{$ticket->id}/anexos", [
        'ficheiro' => $file,
    ]);

    $response->assertStatus(403);
});
