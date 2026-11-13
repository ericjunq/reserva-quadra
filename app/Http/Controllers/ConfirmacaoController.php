<?php

namespace App\Http\Controllers;

use App\Constants\EtapaConfirmacaoConstant;
use App\Http\Requests\ConfirmarReservaRequest;
use App\Http\Resources\ReservaResource;
use App\Services\ConfirmacaoService;

class ConfirmacaoController extends Controller
{
    public function __construct(private ConfirmacaoService $confirmacaoService) {}

    public function show(string $token)
    {
        $reserva = $this->confirmacaoService->resumo($token);
        $etapa = $this->confirmacaoService->etapa($reserva);

        return (new ReservaResource($reserva))->additional([
            'etapa'               => $etapa,
            'pode_confirmar'      => $this->confirmacaoService->podeConfirmar($reserva),
            'prazo_confirmacao'   => $this->confirmacaoService->prazo($reserva),
            'exige_aceite_termos' => $etapa === EtapaConfirmacaoConstant::CONFIRMACAO,
            'termos_versao'       => config('reservas.termos_versao'),
        ]);
    }

    public function confirmar(ConfirmarReservaRequest $request, string $token)
    {
        return new ReservaResource($this->confirmacaoService->confirmar($token, $request->boolean('aceite_termos')));
    }
}
