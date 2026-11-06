<?php

namespace App\Console\Commands;

use App\Services\DataBloqueadaService;
use Illuminate\Console\Command;

class ImportarFeriados extends Command
{
    protected $signature = 'feriados:importar
                            {ano? : Ano a importar (padrão: ano atual)}
                            {--facultativos : Inclui Carnaval, Quarta de Cinzas e Corpus Christi}';

    protected $description = 'Importa os feriados nacionais como datas bloqueadas';

    public function handle(DataBloqueadaService $dataBloqueadaService): int
    {
        $ano = (int) ($this->argument('ano') ?? now()->year);
        $importados = $dataBloqueadaService->importarFeriados($ano, (bool) $this->option('facultativos'));

        $this->info("{$importados} feriado(s) importado(s) para {$ano}.");

        return self::SUCCESS;
    }
}
