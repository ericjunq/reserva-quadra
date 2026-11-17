<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrarRetiradaRequest;
use App\Http\Resources\EquipamentoForaResource;
use App\Http\Resources\ReservaResource;
use App\Models\Reserva;
use App\Services\RetiradaService;

class RetiradaController extends Controller
{
    public function __construct(private RetiradaService $retiradaService) {}

    public function registrar(RegistrarRetiradaRequest $request, Reserva $reserva)
    {
        return new ReservaResource($this->retiradaService->registrar($reserva, $request->validated('itens'), $request->user()));
    }

    public function foraAgora()
    {
        ['total' => $total, 'itens' => $itens] = $this->retiradaService->foraAgora();

        return EquipamentoForaResource::collection($itens)->additional(['total' => $total]);
    }
}
