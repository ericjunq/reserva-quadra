<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCursoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['sometimes', 'string', 'max:100', Rule::unique('cursos', 'nome')->ignore($this->route('curso'))],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
