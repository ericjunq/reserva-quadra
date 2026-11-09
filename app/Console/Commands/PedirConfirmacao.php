<?php

namespace App\Console\Commands;

use App\Services\ConfirmacaoService;
use Illuminate\Console\Command;

class PedirConfirmacao extends Command
{
    protected $signature = 'reservas:pedir-confirmacao';

    protected $description = 'Envia o pedido de confirmação das reservas que acontecem nos próximos dias úteis';

    public function handle(ConfirmacaoService $confirmacaoService): int
    {
        $enviados = $confirmacaoService->pedirPendentes();

        $this->info("{$enviados} pedido(s) de confirmação enviado(s).");

        return self::SUCCESS;
    }
}
