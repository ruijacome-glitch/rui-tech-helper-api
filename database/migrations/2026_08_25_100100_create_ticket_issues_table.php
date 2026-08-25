<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets');
            $table->text('descricao');
            $table->string('resultado')->default('pendente');
            $table->text('observacao')->nullable();
            $table->foreignId('resolvido_por_user_id')->nullable()->constrained('users');
            $table->timestamp('resolvido_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_issues');
    }
};
