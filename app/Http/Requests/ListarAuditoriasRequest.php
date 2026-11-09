<?php

namespace App\Http\Requests;

use App\Constants\AcaoAuditoriaConstant;
use App\Constants\PeriodoConstant;
use App\Constants\TipoAuditavelConstant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarAuditoriasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo'        => ['nullable', Rule::enum(TipoAuditavelConstant::class), 'required_with:registro_id'],
            'registro_id' => ['nullable', 'integer', 'min:1'],
            'admin_id'    => ['nullable', 'integer', 'exists:users,id', 'prohibits:sem_admin'],
            'sem_admin'   => ['nullable', 'in:0,1,true,false'],
            'acao'        => ['nullable', Rule::enum(AcaoAuditoriaConstant::class)],
            'periodo'     => ['nullable', Rule::enum(PeriodoConstant::class), 'prohibits:de,ate'],
            'de'          => ['nullable', 'date_format:Y-m-d'],
            'ate'         => ['nullable', 'date_format:Y-m-d', 'after_or_equal:de'],
        ];
    }

    public function filtros(): array
    {
        $filtros = [
            'tipo'        => $this->filled('tipo') ? TipoAuditavelConstant::from($this->input('tipo')) : null,
            'registro_id' => $this->integer('registro_id') ?: null,
            'admin_id'    => $this->integer('admin_id') ?: null,
            'sem_admin'   => $this->filled('sem_admin') ? $this->boolean('sem_admin') : null,
            'acao'        => $this->input('acao'),
            'de'          => $this->input('de'),
            'ate'         => $this->input('ate'),
        ];

        if ($periodo = $this->input('periodo')) {
            [$filtros['de'], $filtros['ate']] = PeriodoConstant::from($periodo)->intervalo();
        }

        return $filtros;
    }
}
