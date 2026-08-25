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
}
