<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportarReservasRequest;
use App\Services\ExportacaoReservasService;

class ExportacaoReservasController extends Controller
{
    public function __construct(private ExportacaoReservasService $exportacaoService) {}

    public function colunas()
    {
        return response()->json(['data' => $this->exportacaoService->colunasDisponiveis()]);
    }

    public function exportar(ExportarReservasRequest $request)
    {
        return $this->exportacaoService->exportar($request->filtros(), $request->colunas(), $request->input('ordem', 'asc'));
    }
}
