<?php

namespace App\Http\Controllers;

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

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEquipamentoRequest $request)
    {
        $equipamento = $this->equipamentoService->criar($request->validated());

        return (new EquipamentoResource($equipamento))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Equipamento $equipamento)
    {
        return new EquipamentoResource($equipamento);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Equipamento $equipamento)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEquipamentoRequest $request, Equipamento $equipamento)
    {
        $equipamento = $this->equipamentoService->atualizar($equipamento, $request->validated());

        return new EquipamentoResource($equipamento);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Equipamento $equipamento)
    {
        $this->equipamentoService->desativar($equipamento);

        return response()->noContent();
    }
}
