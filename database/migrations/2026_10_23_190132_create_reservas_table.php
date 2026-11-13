<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->date('data_reserva');
            $table->string('horario', 20)->nullable();
            $table->time('hora_inicio');
            $table->time('hora_fim');
            $table->foreignId('espaco_id')->constrained('espacos')->restrictOnDelete();
            $table->foreignId('aluno_id')->nullable()->constrained('alunos')->nullOnDelete();
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('atualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('RESERVADO');
            $table->string('tipo', 30)->default('ALUNO');
            $table->timestamp('confirmar_ate')->nullable();
            $table->timestamp('confirmada_em')->nullable();
            $table->timestamp('termos_aceitos_em')->nullable();
            $table->string('termos_versao', 20)->nullable();
            $table->timestamp('reconfirmacao_pedida_em')->nullable();
            $table->timestamp('reconfirmar_ate')->nullable();
            $table->timestamp('reconfirmada_em')->nullable();
            $table->string('descricao', 255)->nullable();
            $table->text('justificativa_revogacao')->nullable();
            $table->timestamp('revogada_em')->nullable();
            $table->timestamp('cancelada_em')->nullable();
            $table->foreignId('revogada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['espaco_id', 'data_reserva']);
            $table->index(['data_reserva', 'status']);
            $table->index(['aluno_id', 'data_reserva']);
            $table->index(['status', 'confirmar_ate']);
            $table->index(['status', 'reconfirmar_ate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};
