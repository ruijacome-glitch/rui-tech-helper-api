<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conteudos', function (Blueprint $table) {
            $table->string('chave', 100)->primary();
            $table->json('valor');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conteudos');
    }
};
