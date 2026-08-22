<?php
// app/Http/Controllers/Admin/TecnicoController.php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;

class TecnicoController extends Controller
{
    public function index()
    {
        $tecnicos = User::query()
            ->where('role', UserRole::Tecnico)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['tecnicos' => $tecnicos]);
    }
}
