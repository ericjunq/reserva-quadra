<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use Illuminate\Http\Request;
use App\Services\AlunoService;
use App\Http\Requests\AlunoRequest;
use App\Http\Resources\AlunoResource;

class AlunoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(AlunoService $alunoService)
    {
        $alunos = $alunoService->findAll();

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
    public function store(AlunoRequest $request, AlunoService $alunoService)
    {
        $aluno = $alunoService->create($request->validated());

        return new AlunoResource($aluno);
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
