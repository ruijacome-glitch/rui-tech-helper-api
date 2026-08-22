<?php
// tests/Feature/Admin/TecnicoIndexEndpointTest.php

use App\Models\User;

test('admin lista tecnicos para dropdown de atribuicao', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'tecnico', 'name' => 'Tecnico Um']);
    User::factory()->create(['role' => 'tecnico', 'name' => 'Tecnico Dois']);
    User::factory()->create(['role' => 'cliente', 'name' => 'Cliente Nao Aparece']);

    $response = $this->actingAs($admin)->getJson('/api/admin/tecnicos');

    $response->assertOk();
    $response->assertJsonCount(2, 'tecnicos');
    $response->assertJsonStructure(['tecnicos' => [['id', 'name']]]);
});

test('resposta de tecnicos nao inclui password nem paginacao', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'tecnico']);

    $response = $this->actingAs($admin)->getJson('/api/admin/tecnicos');

    $response->assertOk();
    $response->assertJsonMissingPath('meta');
    $response->assertJsonMissingPath('tecnicos.0.password');
});

test('tecnico nao pode aceder a lista de tecnicos', function () {
    $tecnico = User::factory()->create(['role' => 'tecnico']);

    $response = $this->actingAs($tecnico)->getJson('/api/admin/tecnicos');

    $response->assertStatus(403);
});
