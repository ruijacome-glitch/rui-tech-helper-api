<?php

namespace App\Models;

use App\Enums\EquipamentoRegistoTipo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipamentoRegisto extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ticket_id',
        'tipo',
        'user_id',
        'nome_assinante',
        'assinatura_path',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => EquipamentoRegistoTipo::class,
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
