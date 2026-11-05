<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEquipamentoRequest extends FormRequest
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
                'sometimes',
                'string',
                'max:100',
                Rule::unique('equipamentos', 'nome')
                    ->where('espaco_id', $this->route('equipamento')->espaco_id)
                    ->ignore($this->route('equipamento')),
            ],
            'quantidade_total' => ['sometimes', 'integer', 'min:1'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
