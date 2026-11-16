<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('desafios_login', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('desafio_hash', 64)->unique();
            $table->string('codigo_hash', 64);
            $table->timestamp('expira_em');
            $table->unsignedTinyInteger('tentativas')->default(0);
            $table->unsignedTinyInteger('reenvios')->default(0);
            $table->timestamp('ultimo_envio_em');
            $table->timestamp('usado_em')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('expira_em');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desafios_login');
    }
};
