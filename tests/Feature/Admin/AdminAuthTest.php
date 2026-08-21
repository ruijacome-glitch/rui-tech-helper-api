<?php

use App\Models\User;

test('admin can log in and gets redirected', function () {
    $admin = User::factory()->create([
        'password' => bcrypt('password123'),
    ]);

    $response = $this->post('/admin/login', [
        'email' => $admin->email,
        'password' => 'password123',
    ]);

    $response->assertRedirect();
    $this->assertAuthenticatedAs($admin, 'web');
});

test('invalid credentials do not log in', function () {
    $admin = User::factory()->create([
        'password' => bcrypt('password123'),
    ]);

    $response = $this->post('/admin/login', [
        'email' => $admin->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest('web');
});

test('guest sees login form', function () {
    $response = $this->get('/admin/login');

    $response->assertOk();
    $response->assertSee('Login');
});
