<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('espaco_id')->constrained('espacos')->restrictOnDelete();
            $table->string('nome', 100);
            $table->integer('quantidade_total');
            $table->boolean('ativo')->default(true);
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('atualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['espaco_id', 'nome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipamentos');
    }
};
