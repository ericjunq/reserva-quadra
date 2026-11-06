<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use Illuminate\Http\Request;
use App\Services\AlunoService;
use App\Http\Requests\StoreAlunoRequest;
use App\Http\Resources\AlunoResource;

class AlunoController extends Controller
{
    public function __construct(private AlunoService $alunoService) {}

    public function index(Request $request)
    {
        $alunos = $this->alunoService->listarPaginado($this->porPagina($request));

        return AlunoResource::collection($alunos);
    }

    public function store(StoreAlunoRequest $request)
    {
        $aluno = $this->alunoService->criar($request->validated());

        return (new AlunoResource($aluno))->response()->setStatusCode(201);
    }
}
