<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAlunoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            "nome_completo" => ["required", 'string', 'max:150'],
            "registro_academico" => ['required', 'string', 'size:10', 'unique:alunos,registro_academico'],
            "email" => ['required', 'string', 'email', 'max:100'],
            "curso_id" => ['required', 'integer', Rule::exists('cursos', 'id')->where('ativo', true)],
        ];
    }
}
