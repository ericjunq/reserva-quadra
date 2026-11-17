<?php

namespace App\Http\Controllers;

use App\Http\Requests\AceitarTermosApiRequest;
use App\Services\ClienteApiService;
use App\Support\TermosApi;

class AceiteApiController extends Controller
{
    public function __construct(private ClienteApiService $clienteApiService) {}

    public function show(string $token)
    {
        $cliente = $this->clienteApiService->buscarPeloLink($token);

        return response()->json(['data' => [
            'sistema'          => $cliente->nome,
            'organizacao'      => $cliente->organizacao,
            'responsavel_nome' => $cliente->responsavel_nome,
            'finalidade'       => $cliente->finalidade,
            'permissoes'       => array_values(array_filter(
                TermosApi::permissoes(),
                fn (array $permissao) => in_array($permissao['valor'], $cliente->permissoes, true),
            )),
            'renovacao'      => $cliente->acesso_expira_em?->isFuture() ?? false,
            'link_expira_em' => $cliente->aceite_expira_em,
            'termos'         => TermosApi::atuais(),
        ]]);
    }

    public function aceitar(AceitarTermosApiRequest $request, string $token)
    {
        return response()->json(['data' => $this->clienteApiService->aceitar($token)])
            ->header('Cache-Control', 'no-store');
    }

    public function termos()
    {
        return response()->json(['data' => TermosApi::atuais()])
            ->setPublic()
            ->setMaxAge(3600);
    }
}
