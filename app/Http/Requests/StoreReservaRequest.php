<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidaEquipamentosDaReserva;
use App\Http\Requests\Concerns\ValidaHorarioDaReserva;
use App\Rules\AntecedenciaMaxima;
use App\Rules\DataNaoBloqueada;
use App\Rules\DiaUtil;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReservaRequest extends FormRequest
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
            ...$this->regrasHorario(),
            'data_reserva'       => ['required', 'date_format:Y-m-d', 'after_or_equal:today', new DiaUtil, new AntecedenciaMaxima, new DataNaoBloqueada],
            'registro_academico' => ['required', 'string', 'size:10', Rule::exists('alunos', 'registro_academico')->where('ativo', true)],
            'espaco_id'          => ['required', 'integer', Rule::exists('espacos', 'id')->where('ativo', true)],
        ];
    }

    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validarHorarioDoEspaco($validator, respeitarJanela: true),
            fn (Validator $validator) => $this->validarEstoqueDosEquipamentos($validator),
        ];
    }
}
