<?php

namespace Database\Factories;

use App\Models\Equipamento;
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
        ];
    }

    public function compartilhadoCom(Espaco ...$espacos): static
    {
        return $this->afterCreating(
            fn (Equipamento $equipamento) => $equipamento->espacosCompartilhados()->attach(collect($espacos)->pluck('id')),
        );
    }

    public function inativo(): static
    {
        return $this->state(['ativo' => false]);
    }
}
