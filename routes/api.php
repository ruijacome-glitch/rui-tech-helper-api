<?php

use App\Http\Controllers\Auth\SessionController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [SessionController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [SessionController::class, 'me']);
    Route::post('/logout', [SessionController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/ping', fn () => response()->json(['ok' => true]));
});

Route::middleware(['auth:sanctum', 'role:tecnico'])->prefix('tecnico')->group(function () {
    Route::get('/ping', fn () => response()->json(['ok' => true]));
});

Route::middleware(['auth:sanctum', 'role:cliente'])->prefix('cliente')->group(function () {
    Route::get('/ping', fn () => response()->json(['ok' => true]));
});
