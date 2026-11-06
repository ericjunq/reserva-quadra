<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListarAlunosRequest;
use App\Http\Requests\StoreAlunoRequest;
use App\Http\Requests\UpdateAlunoRequest;
use App\Http\Resources\AlunoResource;
use App\Models\Aluno;
use App\Services\AlunoService;
use Illuminate\Http\Request;

class AlunoController extends Controller
{
    public function __construct(private AlunoService $alunoService) {}

    public function index(ListarAlunosRequest $request)
    {
        return AlunoResource::collection(
            $this->alunoService->listarPaginado($this->porPagina($request), $request->filtros())
        );
    }

    public function store(StoreAlunoRequest $request)
    {
        $aluno = $this->alunoService->criar($request->validated());

        return (new AlunoResource($aluno))->response()->setStatusCode(201);
    }

    public function show(Aluno $aluno)
    {
        return new AlunoResource($this->alunoService->detalhar($aluno));
    }

    public function update(UpdateAlunoRequest $request, Aluno $aluno)
    {
        [$aluno, $revogadas] = $this->alunoService->atualizar($aluno, $request->validated(), $request->user());

        return (new AlunoResource($aluno))->additional(['reservas_revogadas' => $revogadas]);
    }

    public function destroy(Request $request, Aluno $aluno)
    {
        [$aluno, $revogadas] = $this->alunoService->desativar($aluno, $request->user());

        return (new AlunoResource($aluno))->additional(['reservas_revogadas' => $revogadas]);
    }
}
