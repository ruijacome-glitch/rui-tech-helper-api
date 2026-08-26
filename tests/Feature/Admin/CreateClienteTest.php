<?php

use App\Enums\UserRole;
use App\Mail\ConviteCliente;
use App\Models\Cliente;
use App\Models\Convite;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('admin creates a cliente and an invite email is sent', function () {
    Mail::fake();
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)->postJson('/api/admin/clientes', [
        'nome' => 'Ana Silva',
        'telefone' => '912345678',
        'email' => 'ana@example.com',
    ]);

    $response->assertCreated();
    $cliente = Cliente::firstWhere('email', 'ana@example.com');
    expect($cliente)->not->toBeNull();
    expect(Convite::where('cliente_id', $cliente->id)->exists())->toBeTrue();
    Mail::assertSent(ConviteCliente::class);
});

test('tecnico cannot create clientes', function () {
    $tecnico = User::factory()->create(['role' => UserRole::Tecnico]);

    $this->actingAs($tecnico)->postJson('/api/admin/clientes', ['nome' => 'Ana Silva'])
        ->assertForbidden();
});

test('email de convite usa layout com botao de activacao', function () {
    Mail::fake();

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->postJson('/api/admin/clientes', [
        'nome' => 'Cliente Novo',
        'email' => 'novo@example.com',
        'telefone' => '912345678',
    ]);

    Mail::assertSent(ConviteCliente::class, function ($mail) {
        $rendered = $mail->render();

        return str_contains($rendered, 'Ativar conta') && str_contains($rendered, 'O Rui dos Computadores');
    });
});
