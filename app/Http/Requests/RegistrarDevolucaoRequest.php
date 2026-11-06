<?php

namespace App\Http\Requests;

use App\Constants\SituacaoDevolucaoConstant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarDevolucaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'itens'                        => ['required', 'array', 'min:1'],
            'itens.*.equipamento_id'       => ['required', 'integer', 'distinct'],
            'itens.*.quantidade_devolvida' => ['required', 'integer', 'min:0'],
            'itens.*.situacao'             => ['required', Rule::enum(SituacaoDevolucaoConstant::class)->except(SituacaoDevolucaoConstant::PENDENTE)],
            'itens.*.observacao'           => [
                'nullable', 'string', 'max:500',
                'required_if:itens.*.situacao,'.SituacaoDevolucaoConstant::DEVOLVIDO_COM_AVARIA->value.','.SituacaoDevolucaoConstant::NAO_DEVOLVIDO->value,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'itens.*.observacao.required_if' => 'Descreva a avaria ou o que não foi devolvido.',
        ];
    }
}
