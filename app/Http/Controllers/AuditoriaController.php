<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListarAuditoriasRequest;
use App\Http\Resources\AuditoriaResource;
use App\Services\AuditoriaService;

class AuditoriaController extends Controller
{
    public function __construct(private AuditoriaService $auditoriaService) {}

    public function index(ListarAuditoriasRequest $request)
    {
        return AuditoriaResource::collection(
            $this->auditoriaService->listarPaginado($this->porPagina($request), $request->filtros())
        );
    }
}
