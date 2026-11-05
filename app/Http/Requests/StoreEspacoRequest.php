<?php

namespace App\Http\Requests;

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
            'nome' => ['required', 'string', 'max:100', 'unique:espacos,nome'],
            'tipo' => ['required', Rule::enum(TipoEspacoConstant::class)],
        ];
    }
}
