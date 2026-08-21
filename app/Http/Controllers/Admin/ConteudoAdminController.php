<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conteudo;
use App\Models\Preco;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConteudoAdminController extends Controller
{
    public function edit(): View
    {
        $contacto = Conteudo::find('contacto')?->valor ?? ['telefone' => '', 'email' => '', 'whatsapp' => ''];
        $testemunho = Conteudo::find('testemunho')?->valor ?? ['citacao' => '', 'atribuicao' => ''];
        $precos = Preco::orderBy('secao')->orderBy('ordem')->get();

        return view('admin.conteudo.edit', compact('contacto', 'testemunho', 'precos'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'contacto.telefone' => ['required', 'string', 'max:50'],
            'contacto.email' => ['required', 'email', 'max:255'],
            'contacto.whatsapp' => ['required', 'string', 'max:255'],
            'testemunho.citacao' => ['required', 'string'],
            'testemunho.atribuicao' => ['required', 'string', 'max:255'],
            'precos' => ['array'],
            'precos.*.titulo' => ['nullable', 'string', 'max:255'],
            'precos.*.valor' => ['required', 'string', 'max:255'],
            'precos.*.nota' => ['nullable', 'string'],
        ]);

        Conteudo::updateOrCreate(['chave' => 'contacto'], ['valor' => $validated['contacto']]);
        Conteudo::updateOrCreate(['chave' => 'testemunho'], ['valor' => $validated['testemunho']]);

        foreach ($validated['precos'] ?? [] as $id => $data) {
            Preco::whereKey($id)->update([
                'titulo' => $data['titulo'] ?? null,
                'valor' => $data['valor'],
                'nota' => $data['nota'] ?? null,
            ]);
        }

        return redirect()->route('admin.conteudo.edit')->with('status', 'Conteúdo actualizado com sucesso.');
    }
}
