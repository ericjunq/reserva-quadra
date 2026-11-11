<?php

namespace App\Models\Concerns;

use App\Support\VersaoDisponibilidade;
use Illuminate\Support\Facades\DB;

trait AtualizaDisponibilidade
{
    protected static function bootAtualizaDisponibilidade(): void
    {
        $avancar = fn () => DB::afterCommit(fn () => VersaoDisponibilidade::avancar());

        static::saved($avancar);
        static::deleted($avancar);
    }
}
