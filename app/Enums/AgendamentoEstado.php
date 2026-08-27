<?php

namespace App\Enums;

enum AgendamentoEstado: string
{
    case Marcado = 'marcado';
    case Confirmado = 'confirmado';
    case Concluido = 'concluido';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Marcado => 'Marcado',
            self::Confirmado => 'Confirmado',
            self::Concluido => 'Concluído',
            self::Cancelado => 'Cancelado',
        };
    }
}
