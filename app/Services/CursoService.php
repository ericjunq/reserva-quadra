<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\Curso;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CursoService
{
    public function listarAtivos(): Collection
    {
        return Curso::where('ativo', true)->orderBy('nome')->get();
    }

    public function listarPaginado(int $porPagina = 15): LengthAwarePaginator
    {
        return Curso::with(Auditoria::RELACOES)->orderBy('nome')->paginate($porPagina);
    }

    public function criar(array $data): Curso
    {
        return Curso::create($data);
    }

    public function atualizar(Curso $curso, array $data): Curso
    {
        $curso->update($data);

        return $curso;
    }

    public function desativar(Curso $curso): void
    {
        $curso->update(['ativo' => false]);
    }
}
