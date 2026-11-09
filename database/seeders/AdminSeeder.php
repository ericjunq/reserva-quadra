<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@email.com'],
            [
                'name'              => 'Eric',
                'password'          => 'Admin',
                'convite_aceito_em' => now(),
            ]
        );
    }
}
