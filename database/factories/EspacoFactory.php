<?php

namespace Database\Factories;

use App\Constants\TipoEspacoConstant;
use Illuminate\Database\Eloquent\Factories\Factory;

class EspacoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nome'                     => 'Sala '.fake()->unique()->numberBetween(100, 999),
            'tipo'                     => TipoEspacoConstant::SALA,
            'ativo'                    => true,
            'limite_semanal_por_curso' => null,
        ];
    }

    public function quadra(?int $limiteSemanalPorCurso = 3): static
    {
        return $this->state(fn () => [
            'nome'                     => 'Quadra '.fake()->unique()->numberBetween(1, 99),
            'tipo'                     => TipoEspacoConstant::QUADRA,
            'limite_semanal_por_curso' => $limiteSemanalPorCurso,
        ]);
    }

    public function laboratorio(): static
    {
        return $this->state(fn () => [
            'nome' => 'Laboratório '.fake()->unique()->numberBetween(1, 99),
            'tipo' => TipoEspacoConstant::LABORATORIO,
        ]);
    }

    public function livre(string $abreAs = '07:00', string $fechaAs = '22:00', int $duracaoMaxima = 120): static
    {
        return $this->state([
            'modo_horario'           => 'LIVRE',
            'abre_as'                => $abreAs,
            'fecha_as'               => $fechaAs,
            'duracao_maxima_minutos' => $duracaoMaxima,
        ]);
    }

    public function inativo(): static
    {
        return $this->state(['ativo' => false]);
    }
}
