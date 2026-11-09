<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReservaResource;
use App\Services\CancelamentoService;

class CancelamentoController extends Controller
{
    public function __construct(private CancelamentoService $cancelamentoService) {}

    public function show(string $token)
    {
        $reserva = $this->cancelamentoService->resumo($token);

        return (new ReservaResource($reserva))
            ->additional(['pode_cancelar' => $this->cancelamentoService->podeCancelar($reserva)]);
    }

    public function cancelar(string $token)
    {
        return new ReservaResource($this->cancelamentoService->cancelar($token));
    }
}
