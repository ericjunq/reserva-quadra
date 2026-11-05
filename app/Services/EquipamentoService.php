<?php

namespace App\Services;

use App\Constants\StatusConstant;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Exceptions\RegraNegocioException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

class EquipamentoService
{
    public function listarAtivos(Espaco $espaco): Collection
    {
        return $espaco->equipamentos()
        ->where('ativo', true)
        ->orderBy('nome')
        ->get();
    }

    public function listarPaginado(int $porPagina = 15, ?int $espacoId = null): LengthAwarePaginator
    {
        return Equipamento::query()
        ->when($espacoId, fn ($q) => $q->where('espaco_id', $espacoId))
        ->orderBy('nome')
        ->paginate($porPagina);
    }

    public function criar(array $data): Equipamento
    {
        return Equipamento::create($data);
    }

    public function atualizar(Equipamento $equipamento, array $data): Equipamento
    {
        if (isset($data['quantidade_total'])) {
            $this->verificarNovaQuantidade($equipamento, $data['quantidade_total']);
        }

        $equipamento->update($data);

        return $equipamento;
    }

    public function desativar (Equipamento $equipamento):void
    {
        $equipamento->update(['ativo' => false]);
    }

    private function verificarNovaQuantidade(Equipamento $equipamento, int $novaQuantidade): void
    {
        $maiorUso = DB::table('item_reservas')
        ->join('reservas', 'reservas.id', '=', 'item_reservas.reserva_id')
        ->where('item_reservas.equipamento_id', $equipamento->id)
        ->where('reservas.status', StatusConstant::RESERVADO->value)
        ->where('reservas.data', '>=', today())
        ->groupBy('reservas.data', 'reservas.horario')
        ->selectRaw('SUM(item_reservas.quantidade) as total')
        ->orderByDesc('total')
        ->value('total') ?? 0;

        if ($novaQuantidade < $maiorUso)
        {
            throw new RegraNegocioException(
                "Já existem {$maiorUso} unidades reservadas em um mesmo horário. A quantidade não pode ser menor que isso.",
                409,
            );
        }
    }
}
