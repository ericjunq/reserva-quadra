<?php

namespace App\Http\Requests;

use App\Rules\NomeUnico;
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
                new NomeUnico('espacos', $this->route('espaco')->id),
            ],
            'tipo' => ['sometimes', Rule::enum(TipoEspacoConstant::class)],
            'ativo' => ['sometimes', 'boolean'],
            'limite_semanal_por_curso' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
