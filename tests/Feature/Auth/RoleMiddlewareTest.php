<?php

use App\Enums\UserRole;
use App\Models\User;

test('tecnico cannot access admin-only routes', function () {
    $tecnico = User::factory()->create(['role' => UserRole::Tecnico]);

    $this->actingAs($tecnico)->getJson('/api/admin/ping')->assertForbidden();
});

test('admin can access admin-only routes', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->getJson('/api/admin/ping')->assertOk();
});

test('cliente can access cliente routes', function () {
    $cliente = User::factory()->create(['role' => UserRole::Cliente]);

    $this->actingAs($cliente)->getJson('/api/cliente/ping')->assertOk();
});

test('guest is unauthorized on protected routes', function () {
    $this->getJson('/api/admin/ping')->assertUnauthorized();
});
