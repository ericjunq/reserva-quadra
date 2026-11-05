<?php

namespace App\Http\Requests;

use App\Constants\TipoEspacoConstant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEspacoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
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
