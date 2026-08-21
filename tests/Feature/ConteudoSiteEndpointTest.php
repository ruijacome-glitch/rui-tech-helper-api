<?php

use App\Models\Conteudo;
use App\Models\Preco;

test('public conteudo-site endpoint returns seeded data shape', function () {
    Conteudo::create(['chave' => 'contacto', 'valor' => ['telefone' => '123', 'email' => 'a@b.pt', 'whatsapp' => 'https://wa.me/123']]);
    Conteudo::create(['chave' => 'testemunho', 'valor' => ['citacao' => 'Óptimo', 'atribuicao' => 'Cliente X']]);
    Preco::create(['secao' => 'home', 'servico' => 'A', 'titulo' => null, 'valor' => '10€', 'nota' => null, 'ordem' => 1]);
    Preco::create(['secao' => 'precario', 'servico' => 'B', 'titulo' => 'Título B', 'valor' => '20€', 'nota' => 'nota', 'ordem' => 1]);

    $response = $this->getJson('/api/public/conteudo-site');

    $response->assertOk();
    $response->assertJson([
        'contacto' => ['telefone' => '123', 'email' => 'a@b.pt', 'whatsapp' => 'https://wa.me/123'],
        'testemunho' => ['citacao' => 'Óptimo', 'atribuicao' => 'Cliente X'],
        'precosHome' => [['servico' => 'A', 'titulo' => 'A', 'valor' => '10€', 'nota' => null]],
        'precarioAreas' => [['servico' => 'B', 'titulo' => 'Título B', 'valor' => '20€', 'nota' => 'nota']],
    ]);
});

test('public conteudo-site endpoint returns empty shape when tables are empty, never 500', function () {
    $response = $this->getJson('/api/public/conteudo-site');

    $response->assertOk();
    $response->assertJson([
        'contacto' => [],
        'testemunho' => [],
        'precosHome' => [],
        'precarioAreas' => [],
    ]);
});
