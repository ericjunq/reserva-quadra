<?php

namespace App\Http\Controllers;

use App\Constants\TipoEspacoConstant;
use App\Http\Requests\StoreEspacoRequest;
use App\Http\Requests\UpdateEspacoRequest;
use App\Http\Resources\EspacoResource;
use App\Models\Espaco;
use App\Services\EspacoService;
use Illuminate\Http\Request;

class EspacoController extends Controller
{
    public function __construct(private EspacoService $espacoService) {}

    /** Pública: espaços ativos (filtro opcional ?tipo=QUADRA). */
    public function ativos(Request $request)
    {
        $tipo = TipoEspacoConstant::tryFrom((string) $request->query('tipo'));

        return EspacoResource::collection($this->espacoService->listarAtivos($tipo));
    }

    public function index(Request $request)
    {
        $porPagina = min((int) $request->query('per_page', 15), 100);
        $tipo = TipoEspacoConstant::tryFrom((string) $request->query('tipo'));

        return EspacoResource::collection($this->espacoService->listarPaginado($porPagina, $tipo));
    }

    public function store(StoreEspacoRequest $request)
    {
        $espaco = $this->espacoService->criar($request->validated());

        return (new EspacoResource($espaco))->response()->setStatusCode(201);
    }

    public function show(Espaco $espaco)
    {
        return new EspacoResource($espaco);
    }

    public function update(UpdateEspacoRequest $request, Espaco $espaco)
    {
        return new EspacoResource($this->espacoService->atualizar($espaco, $request->validated()));
    }

    public function destroy(Espaco $espaco)
    {
        $this->espacoService->desativar($espaco);

        return response()->noContent();
    }
}
