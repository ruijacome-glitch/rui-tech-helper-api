<?php

namespace App\Enums;

enum MovimentoStockTipo: string
{
    case Entrada = 'entrada';
    case Saida = 'saida';
    case Ajuste = 'ajuste';

    public function label(): string
    {
        return match ($this) {
            self::Entrada => 'Entrada',
            self::Saida => 'Saída',
            self::Ajuste => 'Ajuste',
        };
    }
}
