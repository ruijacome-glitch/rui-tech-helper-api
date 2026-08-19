<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ConviteCliente;
use App\Models\Cliente;
use App\Models\Convite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ClienteController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $cliente = Cliente::create($data);

        $plaintextToken = Str::random(64);
        $convite = Convite::create([
            'cliente_id' => $cliente->id,
            'token_hash' => hash('sha256', $plaintextToken),
            'expires_at' => now()->addDays(7),
        ]);

        if ($cliente->email) {
            Mail::to($cliente->email)->send(new ConviteCliente($convite, $plaintextToken));
        }

        return response()->json(['cliente' => $cliente], 201);
    }
}
