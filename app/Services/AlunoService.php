<?php

namespace App\Services;
use App\Models\Aluno;

class AlunoService
{
    public function create($data)
    {
        if (Aluno::whereRegistro_academico($data["registro_academico"])->exists())
            {
                throw new \Exception("Esse RA já foi cadastrado por outro aluno");
            }

        $aluno = Aluno::create($data);

        return $aluno;
    }


    public function findAll()
    {
        $alunos = Aluno::paginate(10);

        return $alunos;
    }
}