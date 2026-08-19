<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Tecnico = 'tecnico';
    case Cliente = 'cliente';
}
