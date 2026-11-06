<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use Illuminate\Http\Request;
use App\Services\AlunoService;
use App\Http\Requests\StoreAlunoRequest;
use App\Http\Resources\AlunoResource;

class AlunoController extends Controller
{
    public function index(Request $request, AlunoService $alunoService)
    {
        $alunos = $alunoService->listarPaginado(min((int) $request->query('per_page', 15), 100));

        return AlunoResource::collection($alunos);
    }

    public function store(StoreAlunoRequest $request, AlunoService $alunoService)
    {
        $aluno = $alunoService->criar($request->validated());

        return (new AlunoResource($aluno))->response()->setStatusCode(201);
    }
}
