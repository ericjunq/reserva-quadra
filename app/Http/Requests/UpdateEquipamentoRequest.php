<?php

namespace App\Http\Requests;

use App\Rules\NomeUnico;
use Illuminate\Foundation\Http\FormRequest;

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
            'quantidade_total' => ['sometimes', 'integer', 'min:1'],
            'ativo'            => ['sometimes', 'boolean'],
            'compartilhavel'   => ['sometimes', 'boolean'],
        ];
    }
}
