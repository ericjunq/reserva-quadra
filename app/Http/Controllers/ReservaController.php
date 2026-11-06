<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListarReservasRequest;
use App\Http\Requests\RevogarReservaRequest;
use App\Http\Requests\StoreEventoRequest;
use App\Http\Requests\StoreReservaRequest;
use App\Http\Resources\ReservaResource;
use App\Models\Reserva;
use App\Services\ReservaService;

class ReservaController extends Controller
{
    public function __construct(private ReservaService $reservaService) {}

    public function index(ListarReservasRequest $request)
    {
        $filtros = $request->filtros();

        return ReservaResource::collection(
            $this->reservaService->listar($filtros, $request->input('ordem', 'asc'), (int) $request->input('per_page', 15))
        )->additional(['totais_por_status' => $this->reservaService->totaisPorStatus($filtros)]);
    }

    public function show(Reserva $reserva)
    {
        return new ReservaResource($reserva->load(ReservaService::RELACOES_ADMIN));
    }

    public function store(StoreReservaRequest $request)
    {
        $reserva = $this->reservaService->create($request->validated());

        return (new ReservaResource($reserva))->response()->setStatusCode(201);
    }

    public function storeEvento(StoreEventoRequest $request)
    {
        $reserva = $this->reservaService->criarEvento($request->validated(), $request->user());

        return (new ReservaResource($reserva))->response()->setStatusCode(201);
    }

    public function revogar(RevogarReservaRequest $request, Reserva $reserva)
    {
        $reserva = $this->reservaService->revogar($reserva, $request->user(), $request->validated('justificativa'));

        return new ReservaResource($reserva);
    }
}
