<?php

namespace App\Http\Controllers;

use App\Http\Requests\EnviarAceiteApiRequest;
use App\Http\Requests\ListarClientesApiRequest;
use App\Http\Requests\StoreClienteApiRequest;
use App\Http\Requests\UpdateClienteApiRequest;
use App\Http\Resources\ClienteApiResource;
use App\Models\ClienteApi;
use App\Services\ClienteApiService;
use App\Support\TermosApi;

class ClienteApiController extends Controller
{
    public function __construct(private ClienteApiService $clienteApiService) {}

    public function index(ListarClientesApiRequest $request)
    {
        return ClienteApiResource::collection(
            $this->clienteApiService->listarPaginado($this->porPagina($request), $request->filtros())
        );
    }

    public function store(StoreClienteApiRequest $request)
    {
        return (new ClienteApiResource($this->clienteApiService->criar($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ClienteApi $clienteApi)
    {
        return new ClienteApiResource($this->clienteApiService->detalhar($clienteApi));
    }

    public function update(UpdateClienteApiRequest $request, ClienteApi $clienteApi)
    {
        return new ClienteApiResource($this->clienteApiService->atualizar($clienteApi, $request->validated()));
    }

    public function destroy(ClienteApi $clienteApi)
    {
        return new ClienteApiResource($this->clienteApiService->desativar($clienteApi));
    }

    public function enviarAceite(EnviarAceiteApiRequest $request, ClienteApi $clienteApi)
    {
        return new ClienteApiResource(
            $this->clienteApiService->enviarLinkDeAceite($clienteApi, $request->boolean('revogar_atual'))
        );
    }

    public function permissoes()
    {
        return response()->json(['data' => TermosApi::permissoes()]);
    }
}
