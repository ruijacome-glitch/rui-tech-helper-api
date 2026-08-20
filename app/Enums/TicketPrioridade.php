<?php

namespace App\Enums;

enum TicketPrioridade: string
{
    case Urgente = 'urgente';
    case Normal = 'normal';
    case Baixa = 'baixa';
}
