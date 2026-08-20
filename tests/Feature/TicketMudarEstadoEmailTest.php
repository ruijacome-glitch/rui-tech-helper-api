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
