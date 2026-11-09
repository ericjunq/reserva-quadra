<?php

namespace App\Services;

use App\Models\Auditoria;
use Illuminate\Pagination\LengthAwarePaginator;

class AuditoriaService
{
    public function listarPaginado(int $porPagina, array $filtros): LengthAwarePaginator
    {
        return Auditoria::query()
            ->with('usuario:id,name')
            ->when($filtros['tipo'] ?? null, fn ($q, $tipo) => $q->where('auditavel_type', $tipo->apelido()))
            ->when($filtros['registro_id'] ?? null, fn ($q, $id) => $q->where('auditavel_id', $id))
            ->when($filtros['admin_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when(($filtros['sem_admin'] ?? null) === true, fn ($q) => $q->whereNull('user_id'))
            ->when(($filtros['sem_admin'] ?? null) === false, fn ($q) => $q->whereNotNull('user_id'))
            ->when($filtros['acao'] ?? null, fn ($q, $acao) => $q->where('acao', $acao))
            ->when($filtros['de'] ?? null, fn ($q, $de) => $q->where('created_at', '>=', "{$de} 00:00:00"))
            ->when($filtros['ate'] ?? null, fn ($q, $ate) => $q->where('created_at', '<=', "{$ate} 23:59:59"))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($porPagina);
    }
}
