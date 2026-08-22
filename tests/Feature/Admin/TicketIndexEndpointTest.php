<?php

use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\User;

function criarClienteParaIndex(string $email = 'cliente-index@example.com'): Cliente
{
    $user = User::factory()->create(['role' => 'cliente']);

    return Cliente::create([
        'user_id' => $user->id,
        'nome' => 'Cliente Index',
        'email' => $email,
        'telefone' => '910000000',
    ]);
}

test('admin lista todos os tickets paginados', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $cliente = criarClienteParaIndex();

    Ticket::factory()->count(3)->create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
    ]);

    $response = $this->actingAs($admin)->getJson('/api/admin/tickets');

    $response->assertOk();
    $response->assertJsonCount(3, 'data');
    $response->assertJsonStructure(['data' => [['id', 'titulo', 'estado', 'categoria', 'prioridade', 'cliente_id', 'tecnico_id']], 'meta']);
});

test('admin filtra tickets por estado', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $cliente = criarClienteParaIndex();

    Ticket::factory()->create(['cliente_id' => $cliente->id, 'categoria' => TicketCategoria::Hardware, 'prioridade' => TicketPrioridade::Normal, 'estado' => TicketEstado::Aberto, 'origem' => TicketOrigem::Admin]);
    Ticket::factory()->create(['cliente_id' => $cliente->id, 'categoria' => TicketCategoria::Hardware, 'prioridade' => TicketPrioridade::Normal, 'estado' => TicketEstado::Resolvido, 'origem' => TicketOrigem::Admin]);

    $response = $this->actingAs($admin)->getJson('/api/admin/tickets?estado=resolvido');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.estado', 'resolvido');
});

test('admin filtro com estado invalido devolve 422', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->getJson('/api/admin/tickets?estado=nao-existe');

    $response->assertStatus(422);
});

test('tecnico ve apenas tickets atribuidos a si', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $outroTecnico = User::factory()->create(['role' => 'tecnico']);
    $cliente = criarClienteParaIndex();

    Ticket::factory()->create(['cliente_id' => $cliente->id, 'tecnico_id' => $tecnico->id, 'categoria' => TicketCategoria::Hardware, 'prioridade' => TicketPrioridade::Normal, 'estado' => TicketEstado::Aberto, 'origem' => TicketOrigem::Admin]);
    Ticket::factory()->create(['cliente_id' => $cliente->id, 'tecnico_id' => $outroTecnico->id, 'categoria' => TicketCategoria::Hardware, 'prioridade' => TicketPrioridade::Normal, 'estado' => TicketEstado::Aberto, 'origem' => TicketOrigem::Admin]);

    $response = $this->actingAs($tecnico)->getJson('/api/tecnico/tickets');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.tecnico_id', $tecnico->id);
});

test('cliente nao pode aceder a lista admin de tickets', function () {
    $cliente = User::factory()->create(['role' => 'cliente']);

    $response = $this->actingAs($cliente)->getJson('/api/admin/tickets');

    $response->assertStatus(403);
});
