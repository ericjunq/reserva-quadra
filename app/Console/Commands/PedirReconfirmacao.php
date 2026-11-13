<?php

namespace App\Console\Commands;

use App\Services\ConfirmacaoService;
use Illuminate\Console\Command;

class PedirReconfirmacao extends Command
{
    protected $signature = 'reservas:pedir-reconfirmacao';

    protected $description = 'Pede uma nova confirmação das reservas que acontecem nos próximos dias úteis';

    public function handle(ConfirmacaoService $confirmacaoService): int
    {
        $enviados = $confirmacaoService->pedirReconfirmacoes();

        $this->info("{$enviados} pedido(s) de reconfirmação enviado(s).");

        return self::SUCCESS;
    }
}
