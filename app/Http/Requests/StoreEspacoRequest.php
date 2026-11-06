<?php

namespace App\Http\Requests;

use App\Rules\NomeUnico;
use App\Constants\TipoEspacoConstant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEspacoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:100', new NomeUnico('espacos')],
            'tipo' => ['required', Rule::enum(TipoEspacoConstant::class)],
            'limite_semanal_por_curso' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
