<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos_contacto', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 80);
            $table->string('contacto_valor', 120);
            $table->string('preferencia', 20);
            $table->string('problema', 120)->nullable();
            $table->text('mensagem');
            $table->string('localidade', 120);
            $table->string('periodo', 40)->nullable();
            $table->boolean('email_enviado')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos_contacto');
    }
};
