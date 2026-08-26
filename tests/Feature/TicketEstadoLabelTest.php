<?php
// tests/Feature/TicketEstadoLabelTest.php
use App\Enums\TicketEstado;

test('label devolve texto PT para cada estado', function () {
    expect(TicketEstado::Aberto->label())->toBe('Recebido');
    expect(TicketEstado::EmAnalise->label())->toBe('Em Diagnóstico');
    expect(TicketEstado::AguardaPeca->label())->toBe('Aguarda Peças');
    expect(TicketEstado::EmCurso->label())->toBe('Em Reparação');
    expect(TicketEstado::EmTestes->label())->toBe('Reparação Concluída');
    expect(TicketEstado::AguardaCliente->label())->toBe('Pronto p/ Levantamento');
    expect(TicketEstado::Resolvido->label())->toBe('Entregue');
    expect(TicketEstado::Cancelado->label())->toBe('Cancelado');
});
