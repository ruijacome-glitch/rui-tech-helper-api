<?php

use App\Enums\UserRole;
use App\Models\Cliente;
use App\Models\User;

function criarClienteComUser(): Cliente
{
    $clienteUser = User::factory()->create(['role' => UserRole::Cliente]);

    return Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Teste',
        'email' => $clienteUser->email,
        'telefone' => '912345678',
    ]);
}

test('admin cria ticket', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $cliente = criarClienteComUser();

    $response = $this->actingAs($admin)->postJson('/api/admin/tickets', [
        'cliente_id' => $cliente->id,
        'categoria' => 'hardware',
        'prioridade' => 'normal',
        'titulo' => 'PC nao liga',
        'descricao' => 'Nao arranca.',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('ticket.estado', 'recebido');
    $response->assertJsonPath('ticket.origem', 'admin');
});

test('admin cria ticket com tecnico atribuido', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $cliente = criarClienteComUser();
    $tecnico = User::factory()->create(['role' => UserRole::Tecnico]);

    $response = $this->actingAs($admin)->postJson('/api/admin/tickets', [
        'cliente_id' => $cliente->id,
        'tecnico_id' => $tecnico->id,
        'categoria' => 'software',
        'prioridade' => 'urgente',
        'titulo' => 'Virus',
        'descricao' => 'Alertas constantes.',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('ticket.tecnico_id', $tecnico->id);
});

test('nao-admin nao pode criar ticket via rota admin', function () {
    $tecnico = User::factory()->create(['role' => UserRole::Tecnico]);
    $cliente = criarClienteComUser();

    $response = $this->actingAs($tecnico)->postJson('/api/admin/tickets', [
        'cliente_id' => $cliente->id,
        'categoria' => 'hardware',
        'prioridade' => 'normal',
        'titulo' => 'PC nao liga',
        'descricao' => 'Nao arranca.',
    ]);

    $response->assertStatus(403);
});

test('cliente cria ticket para si proprio, origem e estado forcados', function () {
    $cliente = criarClienteComUser();

    $response = $this->actingAs($cliente->user)->postJson('/api/cliente/tickets', [
        'categoria' => 'rede',
        'prioridade' => 'baixa',
        'titulo' => 'Wifi lento',
        'descricao' => 'Internet muito lenta em casa.',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('ticket.origem', 'cliente');
    $response->assertJsonPath('ticket.estado', 'recebido');
    $response->assertJsonPath('ticket.cliente_id', $cliente->id);
});

test('cliente sem ficha associada recebe 409 ao criar ticket', function () {
    $user = User::factory()->create(['role' => UserRole::Cliente]);

    $response = $this->actingAs($user)->postJson('/api/cliente/tickets', [
        'categoria' => 'rede',
        'prioridade' => 'baixa',
        'titulo' => 'Wifi lento',
        'descricao' => 'Internet muito lenta.',
    ]);

    $response->assertStatus(409);
});

test('admin nao pode atribuir cliente como tecnico', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $cliente = criarClienteComUser();
    $outroClienteUser = User::factory()->create(['role' => UserRole::Cliente]);

    $response = $this->actingAs($admin)->postJson('/api/admin/tickets', [
        'cliente_id' => $cliente->id,
        'tecnico_id' => $outroClienteUser->id,
        'categoria' => 'hardware',
        'prioridade' => 'normal',
        'titulo' => 'PC nao liga',
        'descricao' => 'Nao arranca.',
    ]);

    $response->assertStatus(422);
});

test('mass assignment de estado e origem e ignorado na rota admin', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $cliente = criarClienteComUser();

    $response = $this->actingAs($admin)->postJson('/api/admin/tickets', [
        'cliente_id' => $cliente->id,
        'categoria' => 'hardware',
        'prioridade' => 'normal',
        'titulo' => 'PC nao liga',
        'descricao' => 'Nao arranca.',
        'estado' => 'entregue',
        'origem' => 'cliente',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('ticket.estado', 'recebido');
    $response->assertJsonPath('ticket.origem', 'admin');
});

test('mass assignment de cliente_id e ignorado na rota cliente', function () {
    $cliente = criarClienteComUser();
    $outroCliente = criarClienteComUser();

    $response = $this->actingAs($cliente->user)->postJson('/api/cliente/tickets', [
        'categoria' => 'rede',
        'prioridade' => 'baixa',
        'titulo' => 'Wifi lento',
        'descricao' => 'Internet muito lenta em casa.',
        'cliente_id' => $outroCliente->id,
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('ticket.cliente_id', $cliente->id);
});

test('categoria invalida falha validacao', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $cliente = criarClienteComUser();

    $response = $this->actingAs($admin)->postJson('/api/admin/tickets', [
        'cliente_id' => $cliente->id,
        'categoria' => 'nonexistent',
        'prioridade' => 'normal',
        'titulo' => 'PC nao liga',
        'descricao' => 'Nao arranca.',
    ]);

    $response->assertStatus(422);
});

test('validacao falha com campos em falta', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $cliente = criarClienteComUser();

    $response = $this->actingAs($admin)->postJson('/api/admin/tickets', [
        'cliente_id' => $cliente->id,
    ]);

    $response->assertStatus(422);
});
