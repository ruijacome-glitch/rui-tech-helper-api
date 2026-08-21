<?php

use App\Enums\PrecoSecao;
use App\Models\Conteudo;
use App\Models\Preco;

test('conteudo persists json valor and uses string primary key', function () {
    $conteudo = Conteudo::create([
        'chave' => 'contacto',
        'valor' => ['telefone' => '+351 91 155 69 01', 'email' => 'ola@oruidoscomputadores.pt'],
    ]);

    $fresh = Conteudo::find('contacto');

    expect($fresh->chave)->toBe('contacto');
    expect($fresh->valor)->toBe(['telefone' => '+351 91 155 69 01', 'email' => 'ola@oruidoscomputadores.pt']);
});

test('preco casts secao to enum and ordem to integer', function () {
    $preco = Preco::create([
        'secao' => PrecoSecao::Home,
        'servico' => 'Diagnóstico',
        'valor' => 'Valor a confirmar',
        'nota' => null,
        'ordem' => '2',
    ]);

    $fresh = Preco::find($preco->id);

    expect($fresh->secao)->toBe(PrecoSecao::Home);
    expect($fresh->ordem)->toBeInt();
    expect($fresh->ordem)->toBe(2);
});
