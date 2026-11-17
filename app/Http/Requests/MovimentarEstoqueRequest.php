<?php

namespace App\Http\Requests;

use App\Constants\TipoMovimentacaoConstant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MovimentarEstoqueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ajuste = $this->input('tipo') === TipoMovimentacaoConstant::AJUSTE->value;

        return [
            'tipo'       => ['required', Rule::enum(TipoMovimentacaoConstant::class)],
            'quantidade' => ['required', 'integer', 'not_in:0', $ajuste ? 'between:-100000,100000' : 'between:1,100000'],
            'estado'     => ['required', 'string', 'min:3', 'max:500'],
            'reserva_id' => [
                Rule::requiredIf($this->input('tipo') === TipoMovimentacaoConstant::NAO_DEVOLVIDO->value),
                'nullable', 'integer', 'exists:reservas,id',
            ],
            'da_manutencao' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['estado' => 'estado do equipamento', 'reserva_id' => 'reserva'];
    }
}
