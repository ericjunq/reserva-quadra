<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sugestoes_dispensadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipamento_id')->constrained('equipamentos')->restrictOnDelete();
            $table->foreignId('espaco_id')->constrained('espacos')->restrictOnDelete();
            $table->timestamp('dispensada_em');
            $table->foreignId('dispensada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['equipamento_id', 'espaco_id']);
            $table->index('espaco_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sugestoes_dispensadas');
    }
};
