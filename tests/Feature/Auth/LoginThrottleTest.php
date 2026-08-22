<?php
// tests/Feature/Auth/LoginThrottleTest.php

use App\Models\User;

test('login e bloqueado apos 5 tentativas falhadas por minuto', function () {
    $user = User::factory()->create(['password' => bcrypt('password123')]);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong']);
    }

    $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong']);

    $response->assertStatus(429);
});

test('login com credenciais certas continua a funcionar dentro do limite', function () {
    $user = User::factory()->create(['password' => bcrypt('password123')]);

    $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password123']);

    $response->assertOk();
});
