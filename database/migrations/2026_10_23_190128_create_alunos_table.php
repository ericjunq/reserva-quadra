<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alunos', function (Blueprint $table) {
            $table->id();
            $table->string('nome_completo', 150);
            $table->string('registro_academico', 10)->unique();
            $table->string('email', 100);
            $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('atualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('curso_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alunos');
    }
};
