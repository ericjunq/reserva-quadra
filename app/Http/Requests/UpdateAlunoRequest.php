<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAlunoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome_completo'      => ['sometimes', 'string', 'max:150'],
            'registro_academico' => ['sometimes', 'string', 'size:10', Rule::unique('alunos', 'registro_academico')->ignore($this->route('aluno'))],
            'email'              => ['sometimes', 'string', 'email', 'max:100'],
            'curso_id'           => ['sometimes', 'integer', Rule::exists('cursos', 'id')->where('ativo', true)],
            'ativo'              => ['sometimes', 'boolean'],
        ];
    }
}
