<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Http\Requests\StoreEquipamentoRequest;
use App\Http\Requests\UpdateEquipamentoRequest;
use App\Http\Resources\EquipamentoResource;
use App\Models\Equipamento;
use App\Models\Espaco;
use Illuminate\Http\Request;
use App\Services\EquipamentoService;

class EquipamentoController extends Controller
{
    public function __construct(private EquipamentoService $equipamentoService){}

    public function index(Request $request)
    {
        $porPagina = min((int) $request->query('per_page', 15), 100);

        return EquipamentoResource::collection(
            $this->equipamentoService->listarPaginado(
                $porPagina,
                $request->integer('espaco_id') ?: null,
                $request->string('nome')->trim()->value() ?: null,
                $request->has('ativo') ? $request->boolean('ativo') : null,
            )
        );
    }

    public function ativos(Espaco $espaco)
    {
        abort_unless($espaco->ativo, 404);

        return EquipamentoResource::collection($this->equipamentoService->listarAtivos($espaco));
    }

    public function create()
    {
    }

    public function store(StoreEquipamentoRequest $request)
    {
        $equipamento = $this->equipamentoService->criar($request->validated());

        return (new EquipamentoResource($equipamento->load(Auditoria::RELACOES)))->response()->setStatusCode(201);
    }

    public function show(Equipamento $equipamento)
    {
        return new EquipamentoResource($equipamento->load(Auditoria::RELACOES));
    }

    public function edit(Equipamento $equipamento)
    {
    }

    public function update(UpdateEquipamentoRequest $request, Equipamento $equipamento)
    {
        $equipamento = $this->equipamentoService->atualizar($equipamento, $request->validated());

        return new EquipamentoResource($equipamento->load(Auditoria::RELACOES));
    }

    public function destroy(Equipamento $equipamento)
    {
        $this->equipamentoService->desativar($equipamento);

        return response()->noContent();
    }
}
