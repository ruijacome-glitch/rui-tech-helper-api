<?php

use App\Enums\UserRole;
use App\Jobs\EmitirFacturaRecibo;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

test('admin marca pagamento pendente como pago', function () {
    Bus::fake();
    [, $orcamento] = criarOrcamentoAprovadoComPagamento();
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)->postJson("/api/admin/orcamentos/{$orcamento->id}/pagamento/marcar-pago");

    $response->assertOk();
    $response->assertJsonPath('pagamento.estado', 'pago');
    $response->assertJsonPath('pagamento.origem', 'manual');
    Bus::assertDispatched(EmitirFacturaRecibo::class);
});

test('tecnico nao pode marcar pagamento como pago', function () {
    [, $orcamento] = criarOrcamentoAprovadoComPagamento();
    $tecnico = User::factory()->create(['role' => UserRole::Tecnico]);

    $this->actingAs($tecnico)->postJson("/api/admin/orcamentos/{$orcamento->id}/pagamento/marcar-pago")
        ->assertForbidden();
});

test('marcar pagamento ja pago devolve 409', function () {
    [, $orcamento] = criarOrcamentoAprovadoComPagamento();
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->postJson("/api/admin/orcamentos/{$orcamento->id}/pagamento/marcar-pago")
        ->assertOk();

    $this->actingAs($admin)->postJson("/api/admin/orcamentos/{$orcamento->id}/pagamento/marcar-pago")
        ->assertStatus(409);
});
