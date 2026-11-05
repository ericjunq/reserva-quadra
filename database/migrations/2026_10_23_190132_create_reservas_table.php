<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->date('data_reserva');
            $table->string('horario', 20);
            $table->foreignId('espaco_id')->constrained('espacos')->restrictOnDelete();
            $table->foreignId("aluno_id")->nullable()->constrained('alunos')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string("status", 20)->default('RESERVADO');
            $table->string('tipo', 30)->default('ALUNO');
            $table->timestamp('confirmacao_enviada_em')->nullable();
            $table->timestamp('confirmada_em')->nullable();
            $table->string('descricao', 255)->nullable();
            $table->text('justificativa_revogacao')->nullable();
            $table->timestamp('revogada_em')->nullable();
            $table->foreignId('revogada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['data_reserva', 'horario', 'espaco_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};
