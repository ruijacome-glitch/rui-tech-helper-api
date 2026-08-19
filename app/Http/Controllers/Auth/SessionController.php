<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json(['user' => $this->serialize($request->user())]);
    }

    public function me(Request $request)
    {
        return response()->json($this->serialize($request->user()));
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // auth:sanctum's Authenticate middleware calls Auth::shouldUse('sanctum'),
        // and Sanctum's RequestGuard permanently caches its resolved user per
        // instance. Forget cached guards so any later check re-resolves against
        // the now-invalidated session instead of the stale cached user.
        Auth::forgetGuards();

        return response()->noContent();
    }

    private function serialize(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
        ];
    }
}
