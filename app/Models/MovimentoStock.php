<?php

namespace App\Models;

use App\Enums\MovimentoStockTipo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimentoStock extends Model
{
    protected $fillable = [
        'peca_id',
        'tipo',
        'quantidade',
        'motivo',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => MovimentoStockTipo::class,
        ];
    }

    public function peca(): BelongsTo
    {
        return $this->belongsTo(Peca::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
