<?php

namespace App\Http\Controllers;

use App\Support\Termos;

class TermosController extends Controller
{
    public function show()
    {
        return response()->json(['data' => Termos::atuais()])
            ->setPublic()
            ->setMaxAge(3600);
    }
}
