<?php

namespace App\Enums;

enum TicketCategoria: string
{
    case Hardware = 'hardware';
    case Software = 'software';
    case Rede = 'rede';
    case Backup = 'backup';
}
