<?php

namespace App\Enums;

enum TicketEstado: string
{
    case Aberto = 'recebido';
    case EmAnalise = 'em_diagnostico';
    case EmCurso = 'em_reparacao';
    case AguardaCliente = 'pronto_levantamento';
    case AguardaPeca = 'aguarda_pecas';
    case EmTestes = 'reparacao_concluida';
    case Resolvido = 'entregue';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Aberto => 'Recebido',
            self::EmAnalise => 'Em Diagnóstico',
            self::AguardaPeca => 'Aguarda Peças',
            self::EmCurso => 'Em Reparação',
            self::EmTestes => 'Reparação Concluída',
            self::AguardaCliente => 'Pronto p/ Levantamento',
            self::Resolvido => 'Entregue',
            self::Cancelado => 'Cancelado',
        };
    }
}
