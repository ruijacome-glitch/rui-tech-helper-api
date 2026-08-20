<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orcamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets');
            $table->unsignedInteger('versao');
            $table->string('estado')->default('pendente');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('decided_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orcamentos');
    }
};
