<?php

namespace App\Http\Requests;

use App\Rules\NomeUnico;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEquipamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => [
                'sometimes',
                'string',
                'max:100',
                new NomeUnico(
                    'equipamentos',
                    $this->route('equipamento')->id,
                    ['espaco_id' => $this->route('equipamento')->espaco_id],
                ),
            ],
            'quantidade_total'         => ['sometimes', 'integer', 'min:1'],
            'ativo'                    => ['sometimes', 'boolean'],
            'espacos_compartilhados'   => ['sometimes', 'array'],
            'espacos_compartilhados.*' => [
                'integer', 'distinct', 'exists:espacos,id', Rule::notIn([$this->route('equipamento')->espaco_id]),
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
