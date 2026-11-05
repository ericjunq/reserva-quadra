<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEquipamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'espaco_id' => ['required', 'integer', 'exists:espacos,id'],
            'nome' => [
                'required', 'string', 'max:100', Rule::unique('equipamentos', 'nome')
                    ->where('espaco_id', $this->input('espaco_id')),
            ],
            'quantidade_total' => ['required', 'integer', 'min:1'],
            'compartilhavel' => ['sometimes', 'boolean'],
        ];
    }
}
