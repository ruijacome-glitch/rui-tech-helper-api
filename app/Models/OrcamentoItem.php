<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrcamentoItem extends Model
{
    public $timestamps = false;

    protected $table = 'orcamento_itens';

    protected $fillable = [
        'orcamento_id',
        'descricao',
        'quantidade',
        'preco_unitario',
    ];

    protected function casts(): array
    {
        return [
            'preco_unitario' => 'decimal:2',
        ];
    }

    public function orcamento(): BelongsTo
    {
        return $this->belongsTo(Orcamento::class);
    }
}
