<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecusarSolicitacaoRequest;
use App\Http\Resources\ReservaResource;
use App\Models\Equipamento;
use App\Models\Reserva;
use App\Services\SolicitacaoService;
use Illuminate\Http\Request;

class SolicitacaoController extends Controller
{
    public function __construct(private SolicitacaoService $solicitacaoService) {}

    public function aprovar(Request $request, Reserva $reserva, Equipamento $equipamento)
    {
        return new ReservaResource($this->solicitacaoService->aprovar($reserva, $equipamento->id, $request->user()));
    }

    public function recusar(RecusarSolicitacaoRequest $request, Reserva $reserva, Equipamento $equipamento)
    {
        return new ReservaResource($this->solicitacaoService->recusar(
            $reserva, $equipamento->id, $request->user(), $request->validated('justificativa'),
        ));
    }
}
