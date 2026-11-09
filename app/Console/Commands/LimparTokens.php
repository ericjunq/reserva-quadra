<?php

namespace App\Console\Commands;

use App\Services\TokenReservaService;
use Illuminate\Console\Command;

class LimparTokens extends Command
{
    protected $signature = 'reservas:limpar-tokens';

    protected $description = 'Apaga os tokens de cancelamento e confirmação usados ou vencidos há mais do que o período de retenção';

    public function handle(TokenReservaService $tokenReservaService): int
    {
        $dias = config('reservas.tokens_retencao_dias');
        $apagados = $tokenReservaService->limparAntigos($dias);

        $this->info("{$apagados} token(s) com mais de {$dias} dias apagado(s).");

        return self::SUCCESS;
    }
}
