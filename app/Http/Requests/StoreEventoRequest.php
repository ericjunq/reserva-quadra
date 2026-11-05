<?php

namespace App\Http\Requests;

use App\Constants\HorarioReservaConstant;
use App\Http\Requests\Concerns\ValidaEquipamentosDaReserva;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventoRequest extends FormRequest
{
    use ValidaEquipamentosDaReserva;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...$this->regrasEquipamentos(),
            'espaco_id'     => ['required', 'integer', Rule::exists('espacos', 'id')->where('ativo', true)],
            'data_reserva'  => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'horario'       => ['required', Rule::enum(HorarioReservaConstant::class)],
            'descricao'     => ['required', 'string', 'max:255'],
            'justificativa' => ['nullable', 'string', 'min:10', 'max:500'],
        ];
    }
}
