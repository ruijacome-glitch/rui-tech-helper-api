<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orcamento_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orcamento_id')->constrained('orcamentos');
            $table->string('descricao');
            $table->unsignedInteger('quantidade');
            $table->decimal('preco_unitario', 10, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orcamento_itens');
    }
};
