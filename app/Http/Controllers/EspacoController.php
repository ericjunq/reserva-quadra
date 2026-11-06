<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Constants\TipoEspacoConstant;
use App\Http\Requests\ConsultarDisponibilidadeRequest;
use App\Http\Requests\StoreEspacoRequest;
use App\Http\Requests\UpdateEspacoRequest;
use App\Http\Resources\EspacoResource;
use App\Models\Espaco;
use App\Services\DisponibilidadeService;
use App\Services\EspacoService;
use Illuminate\Http\Request;

class EspacoController extends Controller
{
    public function __construct(private EspacoService $espacoService) {}

    public function ativos(Request $request)
    {
        $tipo = TipoEspacoConstant::tryFrom((string) $request->query('tipo'));

        return EspacoResource::collection($this->espacoService->listarAtivos($tipo));
    }

    public function disponibilidade(ConsultarDisponibilidadeRequest $request, Espaco $espaco, DisponibilidadeService $disponibilidadeService)
    {
        abort_unless($espaco->ativo, 404);

        return response()->json([
            'data' => $disponibilidadeService->consultar($espaco, $request->input('de'), $request->ate(), $request->curso()),
        ]);
    }

    public function index(Request $request)
    {
        $porPagina = $this->porPagina($request);
        $tipo = TipoEspacoConstant::tryFrom((string) $request->query('tipo'));

        return EspacoResource::collection($this->espacoService->listarPaginado($porPagina, $tipo));
    }

    public function store(StoreEspacoRequest $request)
    {
        $espaco = $this->espacoService->criar($request->validated());

        return (new EspacoResource($espaco->load(Auditoria::RELACOES)))->response()->setStatusCode(201);
    }

    public function show(Espaco $espaco)
    {
        return new EspacoResource($espaco->load(Auditoria::RELACOES));
    }

    public function update(UpdateEspacoRequest $request, Espaco $espaco)
    {
        return new EspacoResource($this->espacoService->atualizar($espaco, $request->validated())->load(Auditoria::RELACOES));
    }

    public function destroy(Espaco $espaco)
    {
        $this->espacoService->desativar($espaco);

        return response()->noContent();
    }
}
