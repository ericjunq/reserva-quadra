<?php

namespace App\Http\Controllers;

use App\Http\Requests\RevogarReservaRequest;
use App\Http\Requests\StoreEventoRequest;
use App\Http\Requests\StoreReservaRequest;
use App\Http\Resources\ReservaResource;
use App\Models\Reserva;
use App\Services\ReservaService;

class ReservaController extends Controller
{
    public function __construct(private ReservaService $reservaService) {}

    /** Pública: reserva feita pelo aluno informando o RA. */
    public function store(StoreReservaRequest $request)
    {
        $reserva = $this->reservaService->create($request->validated());

        return (new ReservaResource($reserva))->response()->setStatusCode(201);
    }

    /** Admin: reserva de evento da faculdade. */
    public function storeEvento(StoreEventoRequest $request)
    {
        $reserva = $this->reservaService->criarEvento($request->validated(), $request->user());

        return (new ReservaResource($reserva))->response()->setStatusCode(201);
    }

    /** Admin: revoga uma reserva com justificativa e avisa o aluno. */
    public function revogar(RevogarReservaRequest $request, Reserva $reserva)
    {
        $reserva = $this->reservaService->revogar($reserva, $request->user(), $request->validated('justificativa'));

        return new ReservaResource($reserva);
    }
}
