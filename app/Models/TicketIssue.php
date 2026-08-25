<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketIssue extends Model
{
    protected $attributes = [
        'resultado' => 'pendente',
    ];

    protected $fillable = [
        'ticket_id',
        'descricao',
        'resultado',
        'observacao',
        'resolvido_por_user_id',
        'resolvido_at',
    ];

    protected function casts(): array
    {
        return [
            'resolvido_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function resolvidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolvido_por_user_id');
    }
}
