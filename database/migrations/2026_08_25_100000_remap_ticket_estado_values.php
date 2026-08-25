<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $mapa = [
        'aberto' => 'recebido',
        'em_analise' => 'em_diagnostico',
        'em_curso' => 'em_reparacao',
        'aguarda_cliente' => 'pronto_levantamento',
        'aguarda_peca' => 'aguarda_pecas',
        'em_testes' => 'reparacao_concluida',
        'resolvido' => 'entregue',
    ];

    public function up(): void
    {
        DB::transaction(function () {
            foreach ($this->mapa as $antigo => $novo) {
                DB::table('tickets')->where('estado', $antigo)->update(['estado' => $novo]);
                DB::table('ticket_eventos')->where('estado_anterior', $antigo)->update(['estado_anterior' => $novo]);
                DB::table('ticket_eventos')->where('estado_novo', $antigo)->update(['estado_novo' => $novo]);
            }
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE tickets ALTER estado SET DEFAULT 'recebido'");
        }
    }

    public function down(): void
    {
        DB::transaction(function () {
            foreach (array_flip($this->mapa) as $novo => $antigo) {
                DB::table('tickets')->where('estado', $novo)->update(['estado' => $antigo]);
                DB::table('ticket_eventos')->where('estado_anterior', $novo)->update(['estado_anterior' => $antigo]);
                DB::table('ticket_eventos')->where('estado_novo', $novo)->update(['estado_novo' => $antigo]);
            }
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE tickets ALTER estado SET DEFAULT 'aberto'");
        }
    }
};
