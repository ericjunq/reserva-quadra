<?php

namespace App\Http\Requests;

use App\Constants\HorarioReservaConstant;
use App\Http\Requests\Concerns\ValidaEquipamentosDaReserva;
use App\Rules\AntecedenciaMaxima;
use App\Rules\DataNaoBloqueada;
use App\Rules\DiaUtil;
use App\Rules\HorarioAindaReservavel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReservaRequest extends FormRequest
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
            'horario'            => ['required', Rule::enum(HorarioReservaConstant::class), new HorarioAindaReservavel],
            'data_reserva'       => ['required', 'date_format:Y-m-d', 'after_or_equal:today', new DiaUtil, new AntecedenciaMaxima, new DataNaoBloqueada],
            'registro_academico' => ['required', 'string', 'size:10', Rule::exists('alunos', 'registro_academico')->where('ativo', true)],
            'espaco_id'          => ['required', 'integer', Rule::exists('espacos', 'id')->where('ativo', true)],
        ];
    }
}
