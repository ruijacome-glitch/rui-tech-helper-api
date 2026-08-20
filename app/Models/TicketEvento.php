<?php

namespace App\Models;

use App\Enums\TicketEstado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketEvento extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'estado_anterior',
        'estado_novo',
        'observacao',
        'observacao_visivel_cliente',
    ];

    protected function casts(): array
    {
        return [
            'estado_anterior' => TicketEstado::class,
            'estado_novo' => TicketEstado::class,
            'observacao_visivel_cliente' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
