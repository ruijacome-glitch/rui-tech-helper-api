<?php

namespace App\Enums;

enum OrcamentoEstado: string
{
    case Pendente = 'pendente';
    case Aprovado = 'aprovado';
    case Rejeitado = 'rejeitado';
}
