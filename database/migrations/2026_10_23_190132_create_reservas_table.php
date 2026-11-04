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
            $table->date('data');
            $table->timestamp("horario", 0);
            $table->foreignId('espaco_id')->constrained('espaco')->restrictOnDelete();
            $table->foreignId("aluno_id")->nullable()->constrained('alunos')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string("status", 20)->default('RESERVADO');
            $table->string('tipo', 30)->default('ALUNO');
            $table->timestamp('confirmacao_enviada_em')->nullable();
            $table->timestamp('confirmada_em')->nullable();
            $table->timestamps();

            $table->index(['data', 'horario', 'espaco_id']);
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
