<?php

namespace App\Models;

use App\Enums\PagamentoEstado;
use App\Enums\PagamentoMetodo;
use App\Enums\PagamentoOrigem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pagamento extends Model
{
    protected $fillable = [
        'orcamento_id',
        'metodo',
        'estado',
        'ifthenpay_request_id',
        'entidade',
        'referencia',
        'telefone',
        'valor',
        'expires_at',
        'paid_at',
        'origem',
        'moloni_document_id',
        'moloni_numero_documento',
    ];

    protected $appends = ['estado_efetivo'];

    protected function casts(): array
    {
        return [
            'metodo' => PagamentoMetodo::class,
            'estado' => PagamentoEstado::class,
            'origem' => PagamentoOrigem::class,
            'valor' => 'decimal:2',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function orcamento(): BelongsTo
    {
        return $this->belongsTo(Orcamento::class);
    }

    public function estaExpirado(): bool
    {
        return $this->estado === PagamentoEstado::Pendente
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    public function getEstadoEfetivoAttribute(): string
    {
        return $this->estaExpirado() ? PagamentoEstado::Expirado->value : $this->estado->value;
    }
}
