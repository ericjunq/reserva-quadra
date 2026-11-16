<?php

namespace App\Console\Commands;

use App\Services\SolicitacaoService;
use Illuminate\Console\Command;

class VencerSolicitacoes extends Command
{
    protected $signature = 'reservas:vencer-solicitacoes';

    protected $description = 'Encerra os pedidos de equipamento sem resposta perto do início da reserva e avisa o aluno';

    public function handle(SolicitacaoService $solicitacaoService): int
    {
        $vencidas = $solicitacaoService->vencerPendentes();

        $this->info("{$vencidas} pedido(s) de equipamento sem resposta encerrado(s).");

        return self::SUCCESS;
    }
}
