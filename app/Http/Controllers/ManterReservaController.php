<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReservaResource;
use App\Services\SolicitacaoService;

class ManterReservaController extends Controller
{
    public function __construct(private SolicitacaoService $solicitacaoService) {}

    public function show(string $token)
    {
        $reserva = $this->solicitacaoService->resumoParaManter($token);

        return (new ReservaResource($reserva))
            ->additional(['pode_manter' => $this->solicitacaoService->podeManter($reserva)]);
    }

    public function manter(string $token)
    {
        return new ReservaResource($this->solicitacaoService->manter($token));
    }
}
