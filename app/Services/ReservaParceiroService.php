<?php

namespace App\Services;

use App\Models\ClienteApi;
use App\Models\Reserva;
use Illuminate\Pagination\LengthAwarePaginator;

class ReservaParceiroService
{
    public function listarPaginado(ClienteApi $cliente, array $filtros, int $porPagina): LengthAwarePaginator
    {
        return $cliente->reservas()
            ->with(ReservaService::RELACOES_PARCEIRO)
            ->when($filtros['de'] ?? null, fn ($q, $de) => $q->where('data_reserva', '>=', $de))
            ->when($filtros['ate'] ?? null, fn ($q, $ate) => $q->where('data_reserva', '<=', $ate))
            ->when($filtros['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderBy('data_reserva')
            ->orderBy('hora_inicio')
            ->orderBy('id')
            ->paginate($porPagina);
    }

    public function detalhar(ClienteApi $cliente, int $reservaId): Reserva
    {
        return $cliente->reservas()->with(ReservaService::RELACOES_PARCEIRO)->whereKey($reservaId)->firstOrFail();
    }
}
