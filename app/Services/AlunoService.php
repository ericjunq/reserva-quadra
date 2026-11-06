<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\Aluno;
use Illuminate\Pagination\LengthAwarePaginator;

class AlunoService
{
    public function criar(array $data): Aluno
    {
        return Aluno::create($data)->load('curso');
    }

    public function listarPaginado(int $porPagina = 15): LengthAwarePaginator
    {
        return Aluno::with(['curso', ...Auditoria::RELACOES])->orderBy('nome_completo')->paginate($porPagina);
    }
}
