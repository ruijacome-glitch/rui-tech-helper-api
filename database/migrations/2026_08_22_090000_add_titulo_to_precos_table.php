<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('precos', function (Blueprint $table) {
            $table->string('titulo')->nullable()->after('servico');
        });

        DB::table('precos')->whereNull('titulo')->update([
            'titulo' => DB::raw('servico'),
        ]);
    }

    public function down(): void
    {
        Schema::table('precos', function (Blueprint $table) {
            $table->dropColumn('titulo');
        });
    }
};
