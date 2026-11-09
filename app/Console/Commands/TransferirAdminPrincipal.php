<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TransferirAdminPrincipal extends Command
{
    protected $signature = 'admin:transferir-principal {email : Email do admin que passa a ser o principal}';

    protected $description = 'Passa o papel de admin principal para outro admin ativo';

    public function handle(): int
    {
        $novo = User::where('email', User::normalizarEmail($this->argument('email')))->first();

        if (! $novo || ! $novo->ativo || ! $novo->conviteAceito()) {
            $this->error('O admin precisa existir, estar ativo e já ter definido a senha.');

            return self::FAILURE;
        }

        if ($novo->principal) {
            $this->info("{$novo->email} já é o admin principal.");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($novo) {
            User::where('principal', true)->lockForUpdate()->get()
                ->each(fn (User $atual) => $atual->forceFill(['principal' => false])->save());

            $novo->forceFill(['principal' => true])->save();
        });

        $this->info("{$novo->email} agora é o admin principal.");

        return self::SUCCESS;
    }
}
