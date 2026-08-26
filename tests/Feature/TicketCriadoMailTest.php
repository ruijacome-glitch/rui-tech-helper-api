<?php
// tests/Feature/TicketCriadoMailTest.php
use App\Enums\TicketCategoria;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Enums\UserRole;
use App\Mail\TicketCriado;
use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('criar ticket envia email TicketCriado quando cliente tem email', function () {
    Mail::fake();
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
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Computador nao arranca.',
    ]);

    Mail::assertSent(TicketCriado::class, fn ($mail) => $mail->hasTo('cliente@example.com')
        && $mail->ticket->is($ticket));
});

test('email TicketCriado contem titulo e link de tracking', function () {
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
        'origem' => TicketOrigem::Admin,
        'titulo' => 'PC nao liga',
        'descricao' => 'Computador nao arranca.',
    ]);

    $rendered = (new TicketCriado($ticket))->render();

    expect($rendered)->toContain('PC nao liga');
    expect($rendered)->toContain('https://tracking.oruidoscomputadores.pt/t/'.$ticket->tracking_token);
});
