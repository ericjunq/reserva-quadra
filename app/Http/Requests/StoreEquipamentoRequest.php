<?php

namespace App\Http\Requests;

use App\Rules\NomeUnico;
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
            'nome'      => [
                'required', 'string', 'max:100', new NomeUnico('equipamentos', escopo: ['espaco_id' => $this->input('espaco_id')]),
            ],
            'quantidade_total'         => ['required', 'integer', 'min:1'],
            'espacos_compartilhados'   => ['sometimes', 'array'],
            'espacos_compartilhados.*' => [
                'integer', 'distinct', 'exists:espacos,id', Rule::notIn([(int) $this->input('espaco_id')]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'espacos_compartilhados.*.not_in' => 'O equipamento já pertence a este espaço.',
        ];
    }
}
