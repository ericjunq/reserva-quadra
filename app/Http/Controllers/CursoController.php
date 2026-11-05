<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCursoRequest;
use App\Http\Requests\UpdateCursoRequest;
use App\Http\Resources\CursoResource;
use App\Models\Curso;
use App\Services\CursoService;
use Illuminate\Http\Request;

class CursoController extends Controller
{
    public function __construct(private CursoService $cursoService) {}

    public function ativos()
    {
        return CursoResource::collection($this->cursoService->listarAtivos());
    }

    public function index(Request $request)
    {
        $porPagina = min((int) $request->query('per_page', 15), 100);

        return CursoResource::collection($this->cursoService->listarPaginado($porPagina));
    }

    public function store(StoreCursoRequest $request)
    {
        $curso = $this->cursoService->criar($request->validated());

        return (new CursoResource($curso))->response()->setStatusCode(201);
    }

    public function show(Curso $curso)
    {
        return new CursoResource($curso);
    }

    public function update(UpdateCursoRequest $request, Curso $curso)
    {
        return new CursoResource($this->cursoService->atualizar($curso, $request->validated()));
    }

    public function destroy(Curso $curso)
    {
        $this->cursoService->desativar($curso);

        return response()->noContent();
    }
}
