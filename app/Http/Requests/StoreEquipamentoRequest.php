<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEquipamentoRequest extends FormRequest
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
            'espaco_id' => ['required', 'integer', 'exists:espacos,id'],
            'nome' => [
                'required', 'string', 'max:100', Rule::unique('equipamentos', 'nome')
                    ->where('espaco_id', $this->input('espaco_id')),
                    ],
                    'quantidadeTotal' => ['required', 'integer', 'min:1'],
        ];
    }
}
