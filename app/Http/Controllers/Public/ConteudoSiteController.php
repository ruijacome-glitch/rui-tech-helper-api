<?php

namespace App\Http\Controllers\Public;

use App\Enums\PrecoSecao;
use App\Http\Controllers\Controller;
use App\Models\Conteudo;
use App\Models\Preco;
use Illuminate\Http\JsonResponse;

class ConteudoSiteController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'contacto' => Conteudo::find('contacto')?->valor ?? [],
            'testemunho' => Conteudo::find('testemunho')?->valor ?? [],
            'precosHome' => $this->precosPorSecao(PrecoSecao::Home),
            'precarioAreas' => $this->precosPorSecao(PrecoSecao::Precario),
        ]);
    }

    private function precosPorSecao(PrecoSecao $secao): array
    {
        return Preco::where('secao', $secao)
            ->orderBy('ordem')
            ->get(['servico', 'titulo', 'valor', 'nota'])
            ->map(fn (Preco $preco) => [
                'servico' => $preco->servico,
                'titulo' => $preco->titulo ?? $preco->servico,
                'valor' => $preco->valor,
                'nota' => $preco->nota,
            ])
            ->all();
    }
}
