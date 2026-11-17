<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListarSugestoesCompartilhamentoRequest;
use App\Http\Resources\EquipamentoResource;
use App\Http\Resources\SugestaoCompartilhamentoResource;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Services\EquipamentoService;
use App\Services\SugestaoCompartilhamentoService;
use Illuminate\Http\Request;

class SugestaoCompartilhamentoController extends Controller
{
    public function __construct(private SugestaoCompartilhamentoService $sugestoes) {}

    public function index(ListarSugestoesCompartilhamentoRequest $request)
    {
        return SugestaoCompartilhamentoResource::collection(
            $this->sugestoes->listar($request->integer('equipamento_id') ?: null)
        );
    }

    public function aceitar(Equipamento $equipamento, Espaco $espaco, EquipamentoService $equipamentoService)
    {
        return new EquipamentoResource($equipamentoService->compartilharCom($equipamento, $espaco));
    }

    public function dispensar(Request $request, Equipamento $equipamento, Espaco $espaco)
    {
        $this->sugestoes->dispensar($equipamento, $espaco, $request->user());

        return response()->json(['message' => 'Sugestão dispensada.']);
    }
}
