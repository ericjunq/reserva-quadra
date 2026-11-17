<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsultarMinhasReservasRequest;
use App\Http\Requests\ReenviarConfirmacaoRequest;
use App\Http\Resources\ReservaResource;
use App\Services\ConfirmacaoService;
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

    public function reenviarConfirmacao(ReenviarConfirmacaoRequest $request, ConfirmacaoService $confirmacaoService)
    {
        $confirmacaoService->reenviar(
            $request->validated('registro_academico'),
            $request->validated('email'),
            (int) $request->validated('reserva_id'),
        );

        return response()->json(['message' => 'Enviamos o email de confirmação de novo. Os links do email anterior não valem mais.']);
    }
}
