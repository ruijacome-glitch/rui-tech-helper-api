<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_checklist_respostas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets');
            $table->string('item_chave');
            $table->boolean('concluido')->default(false);
            $table->foreignId('concluido_por_user_id')->nullable()->constrained('users');
            $table->timestamp('concluido_at')->nullable();
            $table->timestamps();

            $table->unique(['ticket_id', 'item_chave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_checklist_respostas');
    }
};
