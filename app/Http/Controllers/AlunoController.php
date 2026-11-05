<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use Illuminate\Http\Request;
use App\Services\AlunoService;
use App\Http\Requests\StoreAlunoRequest;
use App\Http\Resources\AlunoResource;

class AlunoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, AlunoService $alunoService)
    {
        $alunos = $alunoService->findAll(min((int) $request->query('per_page', 15), 100));

        return AlunoResource::collection($alunos);
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
    public function store(StoreAlunoRequest $request, AlunoService $alunoService)
    {
        $aluno = $alunoService->create($request->validated());

        return (new AlunoResource($aluno))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Aluno $aluno)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Aluno $aluno)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Aluno $aluno)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Aluno $aluno)
    {
        //
    }
}
