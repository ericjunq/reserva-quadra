<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    protected const POR_PAGINA_PADRAO = 15;

    protected const POR_PAGINA_MAXIMO = 100;

    protected function porPagina(Request $request): int
    {
        $porPagina = $request->integer('per_page');

        return $porPagina < 1 ? self::POR_PAGINA_PADRAO : min($porPagina, self::POR_PAGINA_MAXIMO);
    }
}
