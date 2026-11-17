<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes_api', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100);
            $table->string('organizacao', 150);
            $table->string('responsavel_nome', 100);
            $table->string('responsavel_email', 150);
            $table->text('finalidade');
            $table->json('permissoes');
            $table->unsignedSmallInteger('limite_por_minuto');
            $table->boolean('ativo')->default(true);
            $table->string('termos_versao', 20)->nullable();
            $table->timestamp('termos_aceitos_em')->nullable();
            $table->string('aceite_token_hash', 64)->nullable()->unique();
            $table->timestamp('aceite_expira_em')->nullable();
            $table->timestamp('acesso_expira_em')->nullable();
            $table->timestamp('aviso_vencimento_enviado_em')->nullable();
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('atualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['ativo', 'acesso_expira_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes_api');
    }
};
