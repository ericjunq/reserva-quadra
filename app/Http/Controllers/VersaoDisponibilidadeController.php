<?php

namespace App\Http\Controllers;

use App\Support\VersaoDisponibilidade;

class VersaoDisponibilidadeController extends Controller
{
    public function __invoke()
    {
        return response()->json(['data' => ['versao' => VersaoDisponibilidade::atual()]]);
    }
}
