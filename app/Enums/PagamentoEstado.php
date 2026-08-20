<?php

namespace App\Enums;

enum PagamentoEstado: string
{
    case Pendente = 'pendente';
    case Pago = 'pago';
    case Expirado = 'expirado';
    case Cancelado = 'cancelado';
}
