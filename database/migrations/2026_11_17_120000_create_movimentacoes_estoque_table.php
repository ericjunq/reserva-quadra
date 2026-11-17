<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimentacoes_estoque', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipamento_id')->constrained('equipamentos')->restrictOnDelete();
            $table->string('tipo', 20);
            $table->integer('quantidade');
            $table->string('estado', 500);
            $table->foreignId('reserva_id')->nullable()->constrained('reservas')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('quantidade_total_depois');
            $table->integer('em_manutencao_depois');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['equipamento_id', 'created_at']);
            $table->index('reserva_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimentacoes_estoque');
    }
};
