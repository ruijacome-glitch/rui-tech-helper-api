<?php

use App\Http\Controllers\Auth\SessionController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [SessionController::class, 'store']);

Route::post('/convites/{token}/completar', [\App\Http\Controllers\Public\ConviteController::class, 'completar']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [SessionController::class, 'me']);
    Route::post('/logout', [SessionController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/ping', fn () => response()->json(['ok' => true]));
    Route::post('/clientes', [\App\Http\Controllers\Admin\ClienteController::class, 'store']);
    Route::post('/tickets', [\App\Http\Controllers\Tickets\TicketController::class, 'store']);
    Route::patch('/tickets/{ticket}/estado', [\App\Http\Controllers\Tickets\TicketController::class, 'updateEstado']);
    Route::post('/tickets/{ticket}/orcamentos', [\App\Http\Controllers\Tickets\OrcamentoController::class, 'store']);
    Route::post('/tickets/{ticket}/equipamento', [\App\Http\Controllers\Tickets\EquipamentoRegistoController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'role:tecnico'])->prefix('tecnico')->group(function () {
    Route::get('/ping', fn () => response()->json(['ok' => true]));
    Route::patch('/tickets/{ticket}/estado', [\App\Http\Controllers\Tickets\TicketController::class, 'updateEstado']);
    Route::post('/tickets/{ticket}/orcamentos', [\App\Http\Controllers\Tickets\OrcamentoController::class, 'store']);
    Route::post('/tickets/{ticket}/equipamento', [\App\Http\Controllers\Tickets\EquipamentoRegistoController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'role:cliente'])->prefix('cliente')->group(function () {
    Route::get('/ping', fn () => response()->json(['ok' => true]));
    Route::post('/tickets', [\App\Http\Controllers\Tickets\TicketController::class, 'storeCliente']);
    Route::get('/tickets/{ticket}', [\App\Http\Controllers\Tickets\TicketController::class, 'show']);
    Route::post('/orcamentos/{orcamento}/decisao', [\App\Http\Controllers\Tickets\OrcamentoController::class, 'decisao']);
});
