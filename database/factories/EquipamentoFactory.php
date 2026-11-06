<?php

namespace Database\Factories;

use App\Models\Espaco;
use Illuminate\Database\Eloquent\Factories\Factory;

class EquipamentoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'espaco_id'        => Espaco::factory(),
            'nome'             => fake()->unique()->words(2, true),
            'quantidade_total' => fake()->numberBetween(1, 10),
            'ativo'            => true,
            'compartilhavel'   => false,
        ];
    }

    public function compartilhavel(): static
    {
        return $this->state(['compartilhavel' => true]);
    }

    public function inativo(): static
    {
        return $this->state(['ativo' => false]);
    }
}
