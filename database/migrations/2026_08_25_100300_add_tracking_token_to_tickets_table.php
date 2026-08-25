<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    // Sem doctrine/dbal disponivel, Schema::table()->change() nao e fiavel em
    // MySQL nem sqlite. Em vez de adicionar a coluna nullable e depois
    // converter para NOT NULL num segundo passo, criamos a coluna ja
    // nullable()->unique() (um indice unique aceita varios NULLs em ambos os
    // motores, por isso nao ha colisao antes do backfill) e confiamos no
    // model event `Ticket::creating()` para garantir que todo o novo registo
    // recebe sempre um valor. NOT NULL a nivel de BD fica como melhoria
    // futura, nao e necessario para cumprir o spec.
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->uuid('tracking_token')->nullable()->unique()->after('id');
        });

        DB::table('tickets')->whereNull('tracking_token')->orderBy('id')->each(function ($ticket) {
            DB::table('tickets')->where('id', $ticket->id)->update(['tracking_token' => Str::uuid()->toString()]);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique(['tracking_token']);
            $table->dropColumn('tracking_token');
        });
    }
};
