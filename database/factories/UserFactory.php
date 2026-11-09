<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'remember_token'    => Str::random(10),
            'ativo'             => true,
            'convite_aceito_em' => now(),
        ];
    }

    public function principal(): static
    {
        return $this->state(['principal' => true]);
    }

    public function inativo(): static
    {
        return $this->state(['ativo' => false]);
    }

    public function convitePendente(): static
    {
        return $this->state(['convite_aceito_em' => null]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
