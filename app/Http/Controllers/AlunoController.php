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

    public function create()
    {
    }

    public function store(StoreAlunoRequest $request, AlunoService $alunoService)
    {
        $aluno = $alunoService->criar($request->validated());

        return (new AlunoResource($aluno))->response()->setStatusCode(201);
    }

    public function show(Aluno $aluno)
    {
    }

    public function edit(Aluno $aluno)
    {
    }

    public function update(Request $request, Aluno $aluno)
    {
    }

    public function destroy(Aluno $aluno)
    {
    }
}
