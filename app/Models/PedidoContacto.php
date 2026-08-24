<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoContacto extends Model
{
    protected $table = 'pedidos_contacto';

    protected $fillable = [
        'nome',
        'contacto_valor',
        'preferencia',
        'problema',
        'mensagem',
        'localidade',
        'periodo',
        'email_enviado',
    ];

    protected function casts(): array
    {
        return [
            'email_enviado' => 'boolean',
        ];
    }
}
