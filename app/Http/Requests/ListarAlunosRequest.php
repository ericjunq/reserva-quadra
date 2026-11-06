<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListarAlunosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'busca'    => ['nullable', 'string', 'max:100'],
            'curso_id' => ['nullable', 'integer', 'exists:cursos,id'],
            'ativo'    => ['nullable', 'in:0,1,true,false'],
        ];
    }

    public function filtros(): array
    {
        return [
            'busca'    => $this->input('busca'),
            'curso_id' => $this->integer('curso_id') ?: null,
            'ativo'    => $this->filled('ativo') ? $this->boolean('ativo') : null,
        ];
    }
}
