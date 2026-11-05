<?php

namespace App\Http\Requests;

use App\Constants\HorarioReservaConstant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'espaco_id'     => ['required', 'integer', Rule::exists('espacos', 'id')->where('ativo', true)],
            'data'          => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'horario'       => ['required', Rule::enum(HorarioReservaConstant::class)],
            'descricao'     => ['required', 'string', 'max:255'],
            // Obrigatória apenas se o horário já estiver reservado (verificado no service).
            'justificativa' => ['nullable', 'string', 'min:10', 'max:500'],
        ];
    }
}
