<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsultarMinhasReservasRequest;
use App\Http\Resources\ReservaResource;
use App\Services\MinhasReservasService;

class MinhasReservasController extends Controller
{
    public function __construct(private MinhasReservasService $minhasReservasService) {}

    public function index(ConsultarMinhasReservasRequest $request)
    {
        return ReservaResource::collection($this->minhasReservasService->listar(
            $request->validated('registro_academico'),
            $request->validated('email'),
            $request->somenteFuturas(),
            $this->porPagina($request),
        ));
    }
}
