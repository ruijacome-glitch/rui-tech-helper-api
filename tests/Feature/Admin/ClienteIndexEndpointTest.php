<?php

use App\Models\Cliente;
use App\Models\User;

test('admin lista clientes paginados com contagem de intervencoes', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Cliente::create(['nome' => 'Ana Silva', 'email' => 'ana@example.com', 'telefone' => '911000001']);
    Cliente::create(['nome' => 'Bruno Costa', 'email' => 'bruno@example.com', 'telefone' => '911000002']);

    $response = $this->actingAs($admin)->getJson('/api/admin/clientes');

    $response->assertOk();
    $response->assertJsonCount(2, 'data');
    $response->assertJsonStructure(['data' => [['id', 'nome', 'email', 'telefone', 'created_at', 'intervencoes_count']], 'meta']);
});

test('admin pesquisa clientes por nome', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Cliente::create(['nome' => 'Ana Silva', 'email' => 'ana@example.com', 'telefone' => '911000001']);
    Cliente::create(['nome' => 'Bruno Costa', 'email' => 'bruno@example.com', 'telefone' => '911000002']);

    $response = $this->actingAs($admin)->getJson('/api/admin/clientes?search=ana');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.nome', 'Ana Silva');
});

test('tecnico nao pode listar clientes admin', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);

    $response = $this->actingAs($tecnico)->getJson('/api/admin/clientes');

    $response->assertStatus(403);
});
