<?php

namespace App\Http\Requests;

use App\Constants\HorarioReservaConstant;
use App\Constants\PeriodoConstant;
use App\Constants\StatusConstant;
use App\Constants\TipoEspacoConstant;
use App\Constants\TipoReservaConstant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarReservasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'periodo'     => ['nullable', Rule::enum(PeriodoConstant::class), 'prohibits:de,ate'],
            'de'          => ['nullable', 'date_format:Y-m-d'],
            'ate'         => ['nullable', 'date_format:Y-m-d', 'after_or_equal:de'],
            'espaco_id'   => ['nullable', 'integer', 'exists:espacos,id'],
            'tipo_espaco' => ['nullable', Rule::enum(TipoEspacoConstant::class)],
            'curso_id'    => ['nullable', 'integer', 'exists:cursos,id'],
            'busca'       => ['nullable', 'string', 'max:100'],
            'status'      => ['nullable', Rule::enum(StatusConstant::class)],
            'tipo'        => ['nullable', Rule::enum(TipoReservaConstant::class)],
            'horario'     => ['nullable', Rule::enum(HorarioReservaConstant::class)],
            'ordem'       => ['nullable', 'in:asc,desc'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filtros(): array
    {
        $filtros = $this->safe()->except(['periodo', 'ordem', 'per_page', 'colunas']);

        if ($periodo = $this->input('periodo')) {
            [$filtros['de'], $filtros['ate']] = PeriodoConstant::from($periodo)->intervalo();
        }

        if (empty($filtros['de']) && empty($filtros['ate'])) {
            $filtros['de'] = today()->toDateString();
        }

        return $filtros;
    }
}
