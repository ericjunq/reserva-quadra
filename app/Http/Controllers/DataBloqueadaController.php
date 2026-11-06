<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDataBloqueadaRequest;
use App\Http\Requests\UpdateDataBloqueadaRequest;
use App\Http\Resources\DataBloqueadaResource;
use App\Models\DataBloqueada;
use App\Services\DataBloqueadaService;
use Illuminate\Http\Request;

class DataBloqueadaController extends Controller
{
    public function __construct(private DataBloqueadaService $dataBloqueadaService) {}

    public function index(Request $request)
    {
        return DataBloqueadaResource::collection($this->dataBloqueadaService->listarPaginado(
            min((int) $request->query('per_page', 15), 100),
            $request->date('de')?->toDateString(),
            $request->date('ate')?->toDateString(),
            $request->integer('espaco_id') ?: null,
        ));
    }

    public function store(StoreDataBloqueadaRequest $request)
    {
        [$bloqueio, $revogadas] = $this->dataBloqueadaService->criar($request->validated(), $request->user());

        return (new DataBloqueadaResource($bloqueio))
            ->additional(['reservas_revogadas' => $revogadas])
            ->response()
            ->setStatusCode(201);
    }

    public function show(DataBloqueada $dataBloqueada)
    {
        return new DataBloqueadaResource($dataBloqueada->load('espaco'));
    }

    public function update(UpdateDataBloqueadaRequest $request, DataBloqueada $dataBloqueada)
    {
        return new DataBloqueadaResource(
            $this->dataBloqueadaService->atualizarMotivo($dataBloqueada, $request->validated('motivo'))
        );
    }

    public function destroy(DataBloqueada $dataBloqueada)
    {
        $this->dataBloqueadaService->excluir($dataBloqueada);

        return response()->noContent();
    }
}
