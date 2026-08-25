<?php

namespace App\Models;

use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Mail\TicketEstadoAlterado;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Mail;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'cliente_id',
        'tecnico_id',
        'categoria',
        'prioridade',
        'estado',
        'origem',
        'titulo',
        'descricao',
    ];

    protected function casts(): array
    {
        return [
            'categoria' => TicketCategoria::class,
            'prioridade' => TicketPrioridade::class,
            'estado' => TicketEstado::class,
            'origem' => TicketOrigem::class,
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

    public function eventos(): HasMany
    {
        return $this->hasMany(TicketEvento::class);
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(TicketAnexo::class);
    }

    public function orcamentos(): HasMany
    {
        return $this->hasMany(Orcamento::class);
    }

    public function equipamentoRegistos(): HasMany
    {
        return $this->hasMany(EquipamentoRegisto::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(TicketIssue::class);
    }

    public function checklistRespostas(): HasMany
    {
        return $this->hasMany(TicketChecklistResposta::class);
    }

    public function mudarEstado(User $user, TicketEstado $novoEstado, ?string $observacao = null, bool $observacaoVisivelCliente = false): TicketEvento
    {
        $evento = $this->eventos()->create([
            'user_id' => $user->id,
            'estado_anterior' => $this->estado,
            'estado_novo' => $novoEstado,
            'observacao' => $observacao,
            'observacao_visivel_cliente' => $observacaoVisivelCliente,
        ]);

        $this->update(['estado' => $novoEstado]);

        if ($this->cliente->email) {
            Mail::to($this->cliente->email)->send(new TicketEstadoAlterado($evento));
        }

        return $evento;
    }
}
