<?php
// tests/Feature/TicketMudarEstadoEmailTest.php
use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Enums\UserRole;
use App\Mail\TicketEstadoAlterado;
use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('mudarEstado envia email quando cliente tem email', function () {
    Mail::fake();

    $clienteUser = User::factory()->create(['role' => UserRole::Cliente]);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Teste',
        'email' => 'cliente@example.com',
        'telefone' => '912345678',
    ]);
    $ticket = Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Computador nao arranca.',
    ]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $ticket->mudarEstado($admin, TicketEstado::EmAnalise);

    Mail::assertSent(TicketEstadoAlterado::class, fn ($mail) => $mail->hasTo('cliente@example.com'));
});

test('mudarEstado nao envia email quando cliente sem email', function () {
    Mail::fake();

    $clienteUser = User::factory()->create(['role' => UserRole::Cliente]);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Teste',
        'email' => '',
        'telefone' => '912345678',
    ]);
    $ticket = Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Computador nao arranca.',
    ]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $ticket->mudarEstado($admin, TicketEstado::EmAnalise);

    Mail::assertNothingSent();
});

test('email de mudanca de estado mostra label PT e link de tracking', function () {
    config(['services.tracking_url' => 'https://tracking.oruidoscomputadores.pt']);

    $clienteUser = User::factory()->create(['role' => UserRole::Cliente]);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Teste',
        'email' => 'cliente@example.com',
        'telefone' => '912345678',
    ]);
    $ticket = Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Computador nao arranca.',
    ]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $evento = $ticket->mudarEstado($admin, TicketEstado::EmAnalise);

    $rendered = (new App\Mail\TicketEstadoAlterado($evento))->render();

    expect($rendered)->toContain('Em Diagnóstico');
    expect($rendered)->toContain('https://tracking.oruidoscomputadores.pt/t/'.$ticket->tracking_token);
    expect($rendered)->not->toContain('cancelado ');
});

test('email de cancelamento mostra faixa de aviso', function () {
    $clienteUser = User::factory()->create(['role' => UserRole::Cliente]);
    $cliente = Cliente::create([
        'user_id' => $clienteUser->id,
        'nome' => 'Cliente Teste',
        'email' => 'cliente@example.com',
        'telefone' => '912345678',
    ]);
    $ticket = Ticket::create([
        'cliente_id' => $cliente->id,
        'categoria' => TicketCategoria::Hardware,
        'prioridade' => TicketPrioridade::Normal,
        'estado' => TicketEstado::Aberto,
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Computador nao arranca.',
    ]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $evento = $ticket->mudarEstado($admin, TicketEstado::Cancelado);

    $rendered = (new App\Mail\TicketEstadoAlterado($evento))->render();

    expect($rendered)->toContain('Este ticket foi cancelado');
});
