<?php

namespace App\Http\Requests;

use App\Rules\NomeUnico;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCursoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome'  => ['sometimes', 'string', 'max:100', new NomeUnico('cursos', $this->route('curso')->id)],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
