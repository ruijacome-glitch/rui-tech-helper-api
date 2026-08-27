<?php

namespace App\Models;

use App\Enums\AgendamentoEstado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Agendamento extends Model
{
    protected $fillable = [
        'cliente_id',
        'tecnico_id',
        'ticket_id',
        'data_hora',
        'morada',
        'notas',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => AgendamentoEstado::class,
            'data_hora' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
