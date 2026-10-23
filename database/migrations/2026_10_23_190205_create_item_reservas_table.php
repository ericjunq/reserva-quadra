<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('item_reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId("equipamentos")->constrained();
            $table->foreignId("reservas")->constrained()->onDelete("cascade");
            $table->integer("quantidade");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_reservas');
    }
};
