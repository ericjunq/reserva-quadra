<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datas_bloqueadas', function (Blueprint $table) {
            $table->id();
            $table->date('data_inicio');
            $table->date('data_fim');
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fim')->nullable();
            $table->foreignId('espaco_id')->nullable()->constrained('espacos')->cascadeOnDelete();
            $table->string('motivo', 255);
            $table->string('origem', 20)->default('MANUAL');
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['data_inicio', 'data_fim']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datas_bloqueadas');
    }
};
