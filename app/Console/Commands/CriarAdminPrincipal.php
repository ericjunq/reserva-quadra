<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CriarAdminPrincipal extends Command
{
    protected $signature = 'admin:criar-principal
                            {--nome= : Nome do admin}
                            {--email= : Email do admin}';

    protected $description = 'Cria o admin principal (não pode ser desativado nem alterado por outros admins)';

    public function handle(): int
    {
        if ($atual = User::buscarPrincipal()) {
            $this->error("Já existe um admin principal ({$atual->email}). Use admin:transferir-principal para trocar.");

            return self::FAILURE;
        }

        $dados = [
            'nome'                  => $this->option('nome') ?? $this->ask('Nome'),
            'email'                 => User::normalizarEmail($this->option('email') ?? $this->ask('Email')),
            'password'              => $this->secret('Senha (mínimo 8 caracteres, com letras e números)'),
            'password_confirmation' => $this->secret('Confirme a senha'),
        ];

        $validacao = Validator::make($dados, [
            'nome'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        if ($validacao->fails()) {
            foreach ($validacao->errors()->all() as $erro) {
                $this->error($erro);
            }

            return self::FAILURE;
        }

        $admin = new User([
            'name'              => $dados['nome'],
            'email'             => $dados['email'],
            'password'          => $dados['password'],
            'convite_aceito_em' => now(),
        ]);
        $admin->forceFill(['principal' => true])->save();

        $this->info("Admin principal {$admin->email} criado.");

        return self::SUCCESS;
    }
}
