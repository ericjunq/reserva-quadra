<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipamento_id')->constrained('equipamentos')->restrictOnDelete();
            $table->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $table->unsignedInteger('quantidade');
            $table->unsignedInteger('quantidade_retirada')->nullable();
            $table->timestamp('retirado_em')->nullable();
            $table->foreignId('retirado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('situacao_devolucao', 30)->default('PENDENTE');
            $table->unsignedInteger('quantidade_devolvida')->nullable();
            $table->string('observacao_devolucao', 500)->nullable();
            $table->timestamp('devolvido_em')->nullable();
            $table->foreignId('devolvido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('situacao_solicitacao', 20)->nullable();
            $table->timestamp('responder_ate')->nullable();
            $table->boolean('solicitacao_obrigatoria')->default(false);
            $table->timestamp('respondida_em')->nullable();
            $table->foreignId('respondida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('justificativa_recusa', 500)->nullable();
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('atualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['reserva_id', 'equipamento_id']);
            $table->index(['equipamento_id', 'reserva_id']);
            $table->index(['situacao_devolucao', 'reserva_id']);
            $table->index(['situacao_devolucao', 'retirado_em']);
            $table->index(['situacao_solicitacao', 'responder_ate']);
            $table->index(['situacao_solicitacao', 'respondida_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_reservas');
    }
};
