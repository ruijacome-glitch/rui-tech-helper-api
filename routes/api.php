<?php

use App\Http\Controllers\Auth\SessionController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [SessionController::class, 'store']);

Route::post('/convites/{token}/completar', [\App\Http\Controllers\Public\ConviteController::class, 'completar']);

Route::post('/webhooks/ifthenpay', [\App\Http\Controllers\Public\WebhookController::class, 'ifthenpay']);

Route::get('/public/conteudo-site', [\App\Http\Controllers\Public\ConteudoSiteController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [SessionController::class, 'me']);
    Route::post('/logout', [SessionController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/ping', fn () => response()->json(['ok' => true]));
    Route::post('/clientes', [\App\Http\Controllers\Admin\ClienteController::class, 'store']);
    Route::post('/tickets', [\App\Http\Controllers\Tickets\TicketController::class, 'store']);
    Route::get('/tickets', [\App\Http\Controllers\Tickets\TicketController::class, 'indexAdmin']);
    Route::get('/tickets/{ticket}', [\App\Http\Controllers\Tickets\TicketController::class, 'showAdmin']);
    Route::patch('/tickets/{ticket}/atribuir', [\App\Http\Controllers\Tickets\TicketController::class, 'atribuir']);
    Route::get('/orcamentos', [\App\Http\Controllers\Tickets\OrcamentoController::class, 'indexAdmin']);
    Route::patch('/tickets/{ticket}/estado', [\App\Http\Controllers\Tickets\TicketController::class, 'updateEstado']);
    Route::post('/tickets/{ticket}/orcamentos', [\App\Http\Controllers\Tickets\OrcamentoController::class, 'store']);
    Route::post('/tickets/{ticket}/equipamento', [\App\Http\Controllers\Tickets\EquipamentoRegistoController::class, 'store']);
    Route::post('/tickets/{ticket}/anexos', [\App\Http\Controllers\Tickets\AnexoController::class, 'store']);
    Route::post('/orcamentos/{orcamento}/pagamento/marcar-pago', [\App\Http\Controllers\Tickets\PagamentoController::class, 'marcarPago']);
});

Route::middleware(['auth:sanctum', 'role:tecnico'])->prefix('tecnico')->group(function () {
    Route::get('/ping', fn () => response()->json(['ok' => true]));
    Route::get('/tickets', [\App\Http\Controllers\Tickets\TicketController::class, 'indexTecnico']);
    Route::get('/tickets/{ticket}', [\App\Http\Controllers\Tickets\TicketController::class, 'showTecnico']);
    Route::patch('/tickets/{ticket}/estado', [\App\Http\Controllers\Tickets\TicketController::class, 'updateEstado']);
    Route::post('/tickets/{ticket}/orcamentos', [\App\Http\Controllers\Tickets\OrcamentoController::class, 'store']);
    Route::post('/tickets/{ticket}/equipamento', [\App\Http\Controllers\Tickets\EquipamentoRegistoController::class, 'store']);
    Route::post('/tickets/{ticket}/anexos', [\App\Http\Controllers\Tickets\AnexoController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'role:cliente'])->prefix('cliente')->group(function () {
    Route::get('/ping', fn () => response()->json(['ok' => true]));
    Route::post('/tickets', [\App\Http\Controllers\Tickets\TicketController::class, 'storeCliente']);
    Route::get('/tickets/{ticket}', [\App\Http\Controllers\Tickets\TicketController::class, 'show']);
    Route::post('/orcamentos/{orcamento}/decisao', [\App\Http\Controllers\Tickets\OrcamentoController::class, 'decisao']);
    Route::post('/orcamentos/{orcamento}/pagamento', [\App\Http\Controllers\Tickets\PagamentoController::class, 'store']);
});
