<?php

namespace App\Http\Controllers\Public;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Convite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ConviteController extends Controller
{
    public function completar(Request $request, string $token)
    {
        $convite = Convite::where('token_hash', hash('sha256', $token))->first();

        abort_if(! $convite, 404, 'Convite não encontrado.');
        abort_if($convite->isExpired() || $convite->isUsed(), 410, 'Convite expirado ou já utilizado.');

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'morada' => ['nullable', 'string', 'max:255'],
            'nif' => ['nullable', 'digits:9'],
            'password' => ['required', 'string', 'min:10'],
        ]);

        $user = DB::transaction(function () use ($convite, $data) {
            $user = User::create([
                'name' => $convite->cliente->nome,
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => UserRole::Cliente,
            ]);

            $convite->cliente->update([
                'user_id' => $user->id,
                'email' => $data['email'],
                'morada' => $data['morada'] ?? $convite->cliente->morada,
                'nif' => $data['nif'] ?? $convite->cliente->nif,
            ]);

            $convite->update(['used_at' => now()]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['user' => ['id' => $user->id, 'name' => $user->name, 'role' => $user->role->value]]);
    }
}
