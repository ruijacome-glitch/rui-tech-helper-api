<?php

namespace App\Models;

use App\Enums\OrcamentoEstado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Orcamento extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ticket_id',
        'versao',
        'estado',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'estado' => OrcamentoEstado::class,
            'created_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(OrcamentoItem::class);
    }

    public function total(): float
    {
        return (float) $this->itens->sum(fn (OrcamentoItem $item) => $item->quantidade * $item->preco_unitario);
    }

    public static function proximaVersao(Ticket $ticket): int
    {
        return (self::where('ticket_id', $ticket->id)->max('versao') ?? 0) + 1;
    }
}
