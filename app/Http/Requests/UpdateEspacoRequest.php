<?php

namespace App\Http\Requests;

use App\Constants\TipoEspacoConstant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEspacoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => [
                'sometimes', 'string', 'max:100',
                Rule::unique('espacos', 'nome')->ignore($this->route('espaco')),
            ],
            'tipo' => ['sometimes', Rule::enum(TipoEspacoConstant::class)],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
