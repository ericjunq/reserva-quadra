<?php

namespace App\Services;

use App\Models\Aluno;
use Illuminate\Pagination\LengthAwarePaginator;

class AlunoService
{
    // A unicidade do RA é garantida pelo StoreAlunoRequest e pelo índice unique da tabela.
    public function create(array $data): Aluno
    {
        return Aluno::create($data)->load('curso');
    }

    public function findAll(int $porPagina = 15): LengthAwarePaginator
    {
        return Aluno::with('curso')->orderBy('nome_completo')->paginate($porPagina);
    }
}
