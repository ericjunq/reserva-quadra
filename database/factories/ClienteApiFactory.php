<?php

namespace Database\Factories;

use App\Constants\PermissaoApiConstant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClienteApiFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nome'              => fn () => 'Sistema '.fake()->unique()->numerify('####'),
            'organizacao'       => 'Atlética de Exemplo',
            'responsavel_nome'  => 'Bruno Responsável',
            'responsavel_email' => fn () => 'responsavel.'.fake()->unique()->numerify('####').'@parceiro.edu',
            'finalidade'        => 'Reservar a quadra para os treinos das equipes.',
            'permissoes'        => PermissaoApiConstant::valores(),
            'limite_por_minuto' => 60,
            'ativo'             => true,
        ];
    }

    public function comAcesso(): static
    {
        return $this->state(fn () => [
            'termos_versao'     => config('reservas.parceiros.termos_versao'),
            'termos_aceitos_em' => now()->subDay(),
            'acesso_expira_em'  => now()->addMonths(12),
        ]);
    }

    public function desativado(): static
    {
        return $this->state(['ativo' => false]);
    }
}
