<?php

namespace App\Enums;

enum TicketEstado: string
{
    case Aberto = 'aberto';
    case EmAnalise = 'em_analise';
    case EmCurso = 'em_curso';
    case AguardaCliente = 'aguarda_cliente';
    case AguardaPeca = 'aguarda_peca';
    case EmTestes = 'em_testes';
    case Resolvido = 'resolvido';
    case Cancelado = 'cancelado';
}
