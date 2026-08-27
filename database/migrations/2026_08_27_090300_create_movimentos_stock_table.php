<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimentos_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peca_id')->constrained('pecas');
            $table->string('tipo');
            $table->integer('quantidade');
            $table->string('motivo')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimentos_stock');
    }
};
