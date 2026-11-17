<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReservaRequest;
use App\Http\Requests\V1\ListarReservasParceiroRequest;
use App\Http\Resources\V1\ReservaParceiroResource;
use App\Services\CancelamentoService;
use App\Services\ReservaParceiroService;
use App\Services\ReservaService;
use Illuminate\Http\Request;

class ReservaParceiroController extends Controller
{
    public function __construct(private ReservaParceiroService $reservaParceiroService) {}

    public function index(ListarReservasParceiroRequest $request)
    {
        return ReservaParceiroResource::collection(
            $this->reservaParceiroService->listarPaginado($request->user(), $request->filtros(), $this->porPagina($request))
        );
    }

    public function show(Request $request, int $reserva)
    {
        return new ReservaParceiroResource($this->reservaParceiroService->detalhar($request->user(), $reserva));
    }

    public function store(StoreReservaRequest $request, ReservaService $reservaService)
    {
        $reserva = $reservaService->create($request->validated(), cliente: $request->user());

        return (new ReservaParceiroResource($reserva))->response()->setStatusCode(201);
    }

    public function cancelar(Request $request, int $reserva, CancelamentoService $cancelamentoService)
    {
        return new ReservaParceiroResource($cancelamentoService->cancelarPeloParceiro($request->user(), $reserva));
    }
}
