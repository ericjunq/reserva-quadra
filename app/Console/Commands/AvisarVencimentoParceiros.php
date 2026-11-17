<?php

namespace App\Console\Commands;

use App\Services\ClienteApiService;
use Illuminate\Console\Command;

class AvisarVencimentoParceiros extends Command
{
    protected $signature = 'parceiros:avisar-vencimento';

    protected $description = 'Envia ao responsável de cada sistema parceiro com acesso perto de vencer um link para aceitar de novo os termos e gerar um token novo';

    public function handle(ClienteApiService $clienteApiService): int
    {
        $avisados = $clienteApiService->avisarVencimentos();

        $this->info("{$avisados} sistema(s) parceiro(s) avisado(s).");

        return self::SUCCESS;
    }
}
