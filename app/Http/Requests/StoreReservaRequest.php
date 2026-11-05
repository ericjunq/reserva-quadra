<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use App\Constants\HorarioReservaConstant;
use App\Http\Requests\Concerns\ValidaEquipamentosDaReserva;
use Illuminate\Validation\Rule;

class StoreReservaRequest extends FormRequest
{
    use ValidaEquipamentosDaReserva;

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
            ...$this->regrasEquipamentos(),
            "horario" => ['required', Rule::enum(HorarioReservaConstant::class)],
            "data_reserva" => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            "registro_academico" => ['required', 'string', 'size:10', 'exists:alunos,registro_academico'],
            "espaco_id" => ['required', 'integer', Rule::exists('espacos', 'id')->where('ativo', true)],
        ];
    }
}
