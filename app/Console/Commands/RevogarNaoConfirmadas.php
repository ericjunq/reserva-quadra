<?php

namespace App\Console\Commands;

use App\Services\ReservaService;
use Illuminate\Console\Command;

class RevogarNaoConfirmadas extends Command
{
    protected $signature = 'reservas:revogar-nao-confirmadas';

    protected $description = 'Revoga as reservas de hoje que não foram confirmadas no prazo';

    public function handle(ReservaService $reservaService): int
    {
        $revogadas = $reservaService->revogarNaoConfirmadas();

        $this->info("{$revogadas} reserva(s) revogada(s) por falta de confirmação.");

        return self::SUCCESS;
    }
}
