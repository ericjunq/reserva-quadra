<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipamento_compartilhamentos', function (Blueprint $table) {
            $table->foreignId('espaco_id')->constrained('espacos')->restrictOnDelete();
            $table->foreignId('equipamento_id')->constrained('equipamentos')->restrictOnDelete();
            $table->timestamps();

            $table->primary(['espaco_id', 'equipamento_id']);
            $table->index('equipamento_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipamento_compartilhamentos');
    }
};
