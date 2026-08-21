<?php

use App\Enums\UserRole;
use App\Models\Conteudo;
use App\Models\Preco;
use App\Models\User;

test('admin sees current content values on edit page', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    Conteudo::create(['chave' => 'contacto', 'valor' => ['telefone' => '111', 'email' => 'x@y.pt', 'whatsapp' => 'wa']]);

    $response = $this->actingAs($admin, 'web')->get('/admin/conteudo');

    $response->assertOk();
    $response->assertSee('111');
    $response->assertSee('x@y.pt');
});

test('admin can update contacto, testemunho, and preco values', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $preco = Preco::create(['secao' => 'home', 'servico' => 'X', 'valor' => 'old', 'nota' => null, 'ordem' => 1]);

    $response = $this->actingAs($admin, 'web')->put('/admin/conteudo', [
        'contacto' => ['telefone' => '999', 'email' => 'novo@y.pt', 'whatsapp' => 'wa2'],
        'testemunho' => ['citacao' => 'nova citacao', 'atribuicao' => 'novo autor'],
        'precos' => [
            $preco->id => ['valor' => 'new value', 'nota' => 'nova nota'],
        ],
    ]);

    $response->assertRedirect(route('admin.conteudo.edit'));
    expect(Conteudo::find('contacto')->valor)->toBe(['telefone' => '999', 'email' => 'novo@y.pt', 'whatsapp' => 'wa2']);
    expect(Preco::find($preco->id)->valor)->toBe('new value');
    expect(Preco::find($preco->id)->nota)->toBe('nova nota');
});

test('validation failure on bad email leaves database unchanged', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    Conteudo::create(['chave' => 'contacto', 'valor' => ['telefone' => '111', 'email' => 'original@y.pt', 'whatsapp' => 'wa']]);

    $response = $this->actingAs($admin, 'web')->put('/admin/conteudo', [
        'contacto' => ['telefone' => '999', 'email' => 'not-an-email', 'whatsapp' => 'wa2'],
        'testemunho' => ['citacao' => 'x', 'atribuicao' => 'y'],
        'precos' => [],
    ]);

    $response->assertSessionHasErrors('contacto.email');
    expect(Conteudo::find('contacto')->valor['email'])->toBe('original@y.pt');
});

test('non-admin user gets 403 on admin conteudo page', function () {
    $tecnico = User::factory()->create(['role' => UserRole::Tecnico]);

    $response = $this->actingAs($tecnico, 'web')->get('/admin/conteudo');

    $response->assertForbidden();
});
