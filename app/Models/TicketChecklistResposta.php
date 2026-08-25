<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketChecklistResposta extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'item_chave',
        'concluido',
        'concluido_por_user_id',
        'concluido_at',
    ];

    protected function casts(): array
    {
        return [
            'concluido' => 'boolean',
            'concluido_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function concluidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'concluido_por_user_id');
    }
}
