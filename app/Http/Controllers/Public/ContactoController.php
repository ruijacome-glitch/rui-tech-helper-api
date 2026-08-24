<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\NovoPedidoContacto;
use App\Models\Conteudo;
use App\Models\PedidoContacto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactoController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'min:2', 'max:80'],
            'contactoValor' => ['required', 'string', 'max:120'],
            'preferencia' => ['required', 'string', 'in:WhatsApp,Telefone,Email'],
            'problema' => ['nullable', 'string', 'max:120'],
            'mensagem' => ['required', 'string', 'min:10', 'max:1500'],
            'localidade' => ['required', 'string', 'min:2', 'max:120'],
            'periodo' => ['nullable', 'string', 'max:40'],
        ]);

        $pedido = PedidoContacto::create([
            'nome' => $dados['nome'],
            'contacto_valor' => $dados['contactoValor'],
            'preferencia' => $dados['preferencia'],
            'problema' => $dados['problema'] ?? null,
            'mensagem' => $dados['mensagem'],
            'localidade' => $dados['localidade'],
            'periodo' => $dados['periodo'] ?? null,
        ]);

        $emailDestino = Conteudo::find('contacto')?->valor['email'] ?? 'ola@oruidoscomputadores.pt';

        try {
            Mail::to($emailDestino)->send(new NovoPedidoContacto($pedido));
            $pedido->update(['email_enviado' => true]);
        } catch (\Throwable $e) {
            Log::error('Falha a enviar email de pedido de contacto', [
                'pedido_id' => $pedido->id,
                'erro' => $e->getMessage(),
            ]);
        }

        return response()->json(['ok' => true], 201);
    }
}
