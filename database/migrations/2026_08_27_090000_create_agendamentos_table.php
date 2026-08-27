<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agendamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('tecnico_id')->nullable()->constrained('users');
            $table->foreignId('ticket_id')->nullable()->constrained('tickets');
            $table->dateTime('data_hora');
            $table->string('morada')->nullable();
            $table->text('notas')->nullable();
            $table->string('estado')->default('marcado');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agendamentos');
    }
};
