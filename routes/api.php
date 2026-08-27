<?php

use App\Http\Controllers\Auth\SessionController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:5,1');

Route::get('/convites/{token}', [\App\Http\Controllers\Public\ConviteController::class, 'show']);
Route::post('/convites/{token}/completar', [\App\Http\Controllers\Public\ConviteController::class, 'completar']);

Route::match(['get', 'post'], '/webhooks/ifthenpay', [\App\Http\Controllers\Public\WebhookController::class, 'ifthenpay']);

Route::get('/public/conteudo-site', [\App\Http\Controllers\Public\ConteudoSiteController::class, 'index']);

Route::post('/public/contacto', [\App\Http\Controllers\Public\ContactoController::class, 'store'])->middleware('throttle:5,1');

Route::prefix('public/tracking')->middleware('throttle:20,1')->group(function () {
    Route::get('/{token}', [\App\Http\Controllers\Public\TrackingController::class, 'show']);
    Route::post('/{token}/orcamentos/{orcamento}/decisao', [\App\Http\Controllers\Public\TrackingController::class, 'decisaoOrcamento']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [SessionController::class, 'me']);
    Route::post('/logout', [SessionController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/ping', fn () => response()->json(['ok' => true]));
    Route::post('/clientes', [\App\Http\Controllers\Admin\ClienteController::class, 'store']);
    Route::get('/clientes', [\App\Http\Controllers\Admin\ClienteController::class, 'index']);
    Route::get('/clientes/{cliente}', [\App\Http\Controllers\Admin\ClienteController::class, 'show']);
    Route::match(['put', 'patch'], '/clientes/{cliente}', [\App\Http\Controllers\Admin\ClienteController::class, 'update']);
    Route::delete('/clientes/{cliente}', [\App\Http\Controllers\Admin\ClienteController::class, 'destroy']);
    Route::post('/clientes/{cliente}/reenviar-convite', [\App\Http\Controllers\Admin\ClienteController::class, 'reenviarConvite']);
    Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index']);
    Route::post('/tickets', [\App\Http\Controllers\Tickets\TicketController::class, 'store']);
    Route::get('/tickets', [\App\Http\Controllers\Tickets\TicketController::class, 'indexAdmin']);
    Route::get('/tickets/{ticket}', [\App\Http\Controllers\Tickets\TicketController::class, 'showAdmin']);
    Route::patch('/tickets/{ticket}/atribuir', [\App\Http\Controllers\Tickets\TicketController::class, 'atribuir']);
    Route::get('/orcamentos', [\App\Http\Controllers\Tickets\OrcamentoController::class, 'indexAdmin']);
    Route::get('/pagamentos', [\App\Http\Controllers\Tickets\PagamentoController::class, 'indexAdmin']);
    Route::get('/tecnicos', [\App\Http\Controllers\Admin\TecnicoController::class, 'index']);
    Route::patch('/tickets/{ticket}/estado', [\App\Http\Controllers\Tickets\TicketController::class, 'updateEstado']);
    Route::post('/tickets/{ticket}/orcamentos', [\App\Http\Controllers\Tickets\OrcamentoController::class, 'store']);
    Route::post('/tickets/{ticket}/equipamento', [\App\Http\Controllers\Tickets\EquipamentoRegistoController::class, 'store']);
    Route::post('/tickets/{ticket}/anexos', [\App\Http\Controllers\Tickets\AnexoController::class, 'store']);
    Route::post('/tickets/{ticket}/issues', [\App\Http\Controllers\Tickets\TicketIssueController::class, 'store']);
    Route::patch('/tickets/{ticket}/issues/{issue}', [\App\Http\Controllers\Tickets\TicketIssueController::class, 'update']);
    Route::patch('/tickets/{ticket}/checklist/{itemChave}', [\App\Http\Controllers\Tickets\TicketChecklistController::class, 'toggle']);
    Route::post('/orcamentos/{orcamento}/pagamento/marcar-pago', [\App\Http\Controllers\Tickets\PagamentoController::class, 'marcarPago']);
    Route::get('/agendamentos', [\App\Http\Controllers\Admin\AgendamentoController::class, 'index']);
    Route::post('/agendamentos', [\App\Http\Controllers\Admin\AgendamentoController::class, 'store']);
    Route::patch('/agendamentos/{agendamento}', [\App\Http\Controllers\Admin\AgendamentoController::class, 'update']);
    Route::delete('/agendamentos/{agendamento}', [\App\Http\Controllers\Admin\AgendamentoController::class, 'destroy']);
    Route::get('/equipamentos', [\App\Http\Controllers\Admin\EquipamentoController::class, 'index']);
    Route::post('/equipamentos', [\App\Http\Controllers\Admin\EquipamentoController::class, 'store']);
    Route::get('/equipamentos/{equipamento}', [\App\Http\Controllers\Admin\EquipamentoController::class, 'show']);
    Route::patch('/equipamentos/{equipamento}', [\App\Http\Controllers\Admin\EquipamentoController::class, 'update']);
    Route::get('/pecas', [\App\Http\Controllers\Admin\PecaController::class, 'index']);
    Route::post('/pecas', [\App\Http\Controllers\Admin\PecaController::class, 'store']);
    Route::patch('/pecas/{peca}', [\App\Http\Controllers\Admin\PecaController::class, 'update']);
    Route::post('/pecas/{peca}/movimentar', [\App\Http\Controllers\Admin\PecaController::class, 'movimentar']);
});

Route::middleware(['auth:sanctum', 'role:tecnico'])->prefix('tecnico')->group(function () {
    Route::get('/ping', fn () => response()->json(['ok' => true]));
    Route::get('/tickets', [\App\Http\Controllers\Tickets\TicketController::class, 'indexTecnico']);
    Route::get('/tickets/{ticket}', [\App\Http\Controllers\Tickets\TicketController::class, 'showTecnico']);
    Route::patch('/tickets/{ticket}/estado', [\App\Http\Controllers\Tickets\TicketController::class, 'updateEstado']);
    Route::post('/tickets/{ticket}/orcamentos', [\App\Http\Controllers\Tickets\OrcamentoController::class, 'store']);
    Route::post('/tickets/{ticket}/equipamento', [\App\Http\Controllers\Tickets\EquipamentoRegistoController::class, 'store']);
    Route::post('/tickets/{ticket}/anexos', [\App\Http\Controllers\Tickets\AnexoController::class, 'store']);
    Route::post('/tickets/{ticket}/issues', [\App\Http\Controllers\Tickets\TicketIssueController::class, 'store']);
    Route::patch('/tickets/{ticket}/issues/{issue}', [\App\Http\Controllers\Tickets\TicketIssueController::class, 'update']);
    Route::patch('/tickets/{ticket}/checklist/{itemChave}', [\App\Http\Controllers\Tickets\TicketChecklistController::class, 'toggle']);
});

Route::middleware(['auth:sanctum', 'role:cliente'])->prefix('cliente')->group(function () {
    Route::get('/ping', fn () => response()->json(['ok' => true]));
    Route::post('/tickets', [\App\Http\Controllers\Tickets\TicketController::class, 'storeCliente']);
    Route::get('/tickets/{ticket}', [\App\Http\Controllers\Tickets\TicketController::class, 'show']);
    Route::post('/orcamentos/{orcamento}/decisao', [\App\Http\Controllers\Tickets\OrcamentoController::class, 'decisao']);
    Route::post('/orcamentos/{orcamento}/pagamento', [\App\Http\Controllers\Tickets\PagamentoController::class, 'store']);
});
