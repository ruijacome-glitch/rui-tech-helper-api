<?php

namespace Database\Seeders;

use App\Models\Conteudo;
use App\Models\Preco;
use Illuminate\Database\Seeder;

class ConteudoConfiguravelSeeder extends Seeder
{
    public function run(): void
    {
        Conteudo::updateOrCreate(
            ['chave' => 'contacto'],
            ['valor' => [
                'telefone' => '+351 91 155 69 01',
                'email' => 'ola@oruidoscomputadores.pt',
                'whatsapp' => 'https://wa.me/351911556901',
            ]]
        );

        Conteudo::updateOrCreate(
            ['chave' => 'testemunho'],
            ['valor' => [
                'citacao' => 'Finalmente alguém que explica sem complicar.',
                'atribuicao' => 'Exemplo editorial — a validar antes da publicação',
            ]]
        );

        $precosHome = [
            ['servico' => 'Diagnóstico', 'valor' => 'Valor a confirmar', 'nota' => 'Avaliação do problema e explicação do que é preciso fazer.', 'ordem' => 1],
            ['servico' => 'Assistência remota', 'valor' => 'Valor a confirmar', 'nota' => 'Resolução à distância, contigo a acompanhar.', 'ordem' => 2],
            ['servico' => 'Assistência ao domicílio', 'valor' => 'Valor a confirmar', 'nota' => 'Deslocação em Cascais e arredores.', 'ordem' => 3],
        ];

        foreach ($precosHome as $item) {
            Preco::updateOrCreate(
                ['secao' => 'home', 'servico' => $item['servico']],
                ['valor' => $item['valor'], 'nota' => $item['nota'], 'ordem' => $item['ordem']]
            );
        }

        $precarioAreas = [
            ['servico' => 'Limpeza e optimização', 'valor' => 'Valor a confirmar', 'nota' => 'Limpeza física, arranque, espaço em disco e desempenho geral.', 'ordem' => 1],
            ['servico' => 'Instalação e configuração', 'valor' => 'Valor a confirmar', 'nota' => 'Sistema, contas, email, cópias de segurança e equipamento novo.', 'ordem' => 2],
            ['servico' => 'Redes e periféricos', 'valor' => 'Valor a confirmar', 'nota' => 'Wi-Fi, routers, repetidores, impressoras e equipamento partilhado.', 'ordem' => 3],
            ['servico' => 'Recuperação de dados', 'valor' => 'Mediante avaliação', 'nota' => 'Depende sempre do estado do equipamento. Nunca há garantia de recuperação.', 'ordem' => 4],
        ];

        foreach ($precarioAreas as $item) {
            Preco::updateOrCreate(
                ['secao' => 'precario', 'servico' => $item['servico']],
                ['valor' => $item['valor'], 'nota' => $item['nota'], 'ordem' => $item['ordem']]
            );
        }
    }
}
