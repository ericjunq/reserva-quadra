<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('espacos', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100)->unique();
            $table->string('tipo', 30);
            $table->boolean('ativo')->default(true);
            $table->unsignedTinyInteger('limite_semanal_por_curso')->nullable();
            $table->string('modo_horario', 10)->default('FIXO');
            $table->time('abre_as')->nullable();
            $table->time('fecha_as')->nullable();
            $table->unsignedSmallInteger('duracao_maxima_minutos')->nullable();
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('atualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('espacos');
    }
};
