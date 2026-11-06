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
            $table->foreignId("equipamento_id")->constrained('equipamentos')->restrictOnDelete();
            $table->foreignId("reserva_id")->constrained('reservas')->cascadeOnDelete();
            $table->unsignedInteger("quantidade");
            $table->timestamps();

            $table->unique(['reserva_id', 'equipamento_id']);
            $table->index(['equipamento_id', 'reserva_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_reservas');
    }
};
