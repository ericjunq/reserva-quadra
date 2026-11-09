<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $dados = config('reservas.admin_inicial');

        if (blank($dados['email']) || blank($dados['senha'])) {
            $this->command?->warn('ADMIN_INICIAL_EMAIL e ADMIN_INICIAL_SENHA vazios: nenhum admin criado. Use admin:criar-principal.');

            return;
        }

        $admin = User::firstOrCreate(
            ['email' => User::normalizarEmail($dados['email'])],
            [
                'name'              => $dados['nome'] ?: 'Admin',
                'password'          => $dados['senha'],
                'convite_aceito_em' => now(),
            ]
        );

        if (! User::buscarPrincipal()) {
            $admin->forceFill(['principal' => true])->save();
        }
    }
}
