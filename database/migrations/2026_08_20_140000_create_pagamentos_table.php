<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orcamento_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('metodo')->nullable();
            $table->string('estado')->default('pendente');
            $table->string('ifthenpay_request_id')->nullable();
            $table->string('entidade')->nullable();
            $table->string('referencia')->nullable();
            $table->string('telefone')->nullable();
            $table->decimal('valor', 10, 2);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('origem')->nullable();
            $table->string('moloni_document_id')->nullable();
            $table->string('moloni_numero_documento')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagamentos');
    }
};
