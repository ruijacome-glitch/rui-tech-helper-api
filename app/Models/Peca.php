<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Peca extends Model
{
    protected $fillable = [
        'nome',
        'descricao',
        'preco_custo',
        'preco_venda',
        'quantidade_atual',
        'stock_minimo',
    ];

    protected function casts(): array
    {
        return [
            'preco_custo' => 'decimal:2',
            'preco_venda' => 'decimal:2',
            'quantidade_atual' => 'integer',
            'stock_minimo' => 'integer',
        ];
    }

    public function movimentos(): HasMany
    {
        return $this->hasMany(MovimentoStock::class);
    }

    public function stockBaixo(): bool
    {
        return $this->quantidade_atual <= $this->stock_minimo;
    }
}
