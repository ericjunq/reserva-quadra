<?php

namespace Database\Factories;

use App\Models\Curso;
use Illuminate\Database\Eloquent\Factories\Factory;

class AlunoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nome_completo'      => fake('pt_BR')->name(),
            'registro_academico' => fake()->unique()->numerify('##########'),
            'email'              => fake()->unique()->safeEmail(),
            'curso_id'           => Curso::factory(),
        ];
    }
}
