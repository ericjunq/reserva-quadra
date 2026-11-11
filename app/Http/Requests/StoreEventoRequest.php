<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidaEquipamentosDaReserva;
use App\Http\Requests\Concerns\ValidaHorarioDaReserva;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEventoRequest extends FormRequest
{
    use ValidaEquipamentosDaReserva, ValidaHorarioDaReserva;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->arredondarIntervalo();
    }

    public function rules(): array
    {
        return [
            ...$this->regrasEquipamentos(),
            'espaco_id'    => ['required', 'integer', Rule::exists('espacos', 'id')->where('ativo', true)],
            'data_reserva' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            ...$this->regrasHorario(),
            'descricao'     => ['required', 'string', 'max:255'],
            'justificativa' => ['nullable', 'string', 'min:10', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validarHorarioDoEspaco($validator, respeitarJanela: false),
            fn (Validator $validator) => $this->validarEstoqueDosEquipamentos($validator),
        ];
    }
}
