<?php

namespace App\Repositories;

use App\Models\Reserva;
use Illuminate\Database\Eloquent\Builder;

class ReservaRepository
{
    public function filtrar(array $filtros): Builder
    {
        return Reserva::query()
            ->when($filtros['de'] ?? null, fn ($q, $de) => $q->where('data_reserva', '>=', $de))
            ->when($filtros['ate'] ?? null, fn ($q, $ate) => $q->where('data_reserva', '<=', $ate))
            ->when($filtros['espaco_id'] ?? null, fn ($q, $id) => $q->where('espaco_id', $id))
            ->when($filtros['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filtros['tipo'] ?? null, fn ($q, $tipo) => $q->where('tipo', $tipo))
            ->when($filtros['horario'] ?? null, fn ($q, $horario) => $q->where('horario', $horario))
            ->when($filtros['tipo_espaco'] ?? null, fn ($q, $tipo) => $q->whereHas('espaco', fn ($e) => $e->where('tipo', $tipo)))
            ->when($filtros['curso_id'] ?? null, fn ($q, $curso) => $q->whereHas('aluno', fn ($a) => $a->where('curso_id', $curso)))
            ->when($filtros['busca'] ?? null, fn ($q, $busca) => $q->whereHas('aluno', fn ($a) => $a->where(fn ($w) => $w
                ->whereLike('nome_completo', "%{$busca}%", caseSensitive: false)
                ->orWhereLike('registro_academico', "%{$busca}%", caseSensitive: false))));
    }

    public function ordenar(Builder $query, string $ordem = 'asc'): Builder
    {
        return $query
            ->orderBy('data_reserva', $ordem)
            ->orderBy('hora_inicio', $ordem)
            ->orderBy('id', $ordem);
    }
}
