<?php

namespace App\Repositories;

use App\Constants\StatusConstant;
use App\Models\Equipamento;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DisponibilidadeEquipamentoRepository
{
    public function emUso(array $ids, string $data, string $inicio, string $fim): Collection
    {
        if (! $ids) {
            return collect();
        }

        return $this->reservasAtivas()
            ->whereIn('item_reservas.equipamento_id', $ids)
            ->where('reservas.data_reserva', $data)
            ->where('reservas.hora_inicio', '<', $fim)
            ->where('reservas.hora_fim', '>', $inicio)
            ->groupBy('item_reservas.equipamento_id')
            ->selectRaw('item_reservas.equipamento_id, SUM(item_reservas.quantidade) as total')
            ->pluck('total', 'equipamento_id')
            ->map(fn ($total) => (int) $total);
    }

    public function disponiveis(iterable $equipamentos, string $data, string $inicio, string $fim): Collection
    {
        $equipamentos = collect($equipamentos);
        $emUso = $this->emUso($equipamentos->pluck('id')->all(), $data, $inicio, $fim);

        return $equipamentos->mapWithKeys(fn (Equipamento $equipamento) => [
            $equipamento->id => max($equipamento->quantidade_total - ($emUso[$equipamento->id] ?? 0), 0),
        ]);
    }

    public function usoNoPeriodo(array $ids, string $de, string $ate): Collection
    {
        if (! $ids) {
            return collect();
        }

        return $this->reservasAtivas()
            ->whereIn('item_reservas.equipamento_id', $ids)
            ->whereBetween('reservas.data_reserva', [$de, $ate])
            ->groupBy('reservas.data_reserva', 'reservas.hora_inicio', 'reservas.hora_fim', 'item_reservas.equipamento_id')
            ->selectRaw('reservas.data_reserva, reservas.hora_inicio, reservas.hora_fim, item_reservas.equipamento_id, SUM(item_reservas.quantidade) as total')
            ->get()
            ->map(fn ($linha) => (object) [
                'data'           => (string) $linha->data_reserva,
                'hora_inicio'    => (string) $linha->hora_inicio,
                'hora_fim'       => (string) $linha->hora_fim,
                'equipamento_id' => (int) $linha->equipamento_id,
                'total'          => (int) $linha->total,
            ]);
    }

    public function maiorUsoFuturo(Equipamento $equipamento): int
    {
        return (int) ($this->reservasAtivas()
            ->where('item_reservas.equipamento_id', $equipamento->id)
            ->where('reservas.data_reserva', '>=', today()->toDateString())
            ->groupBy('reservas.data_reserva', 'reservas.hora_inicio')
            ->selectRaw('SUM(item_reservas.quantidade) as total')
            ->orderByDesc('total')
            ->value('total') ?? 0);
    }

    private function reservasAtivas(): Builder
    {
        return DB::table('item_reservas')
            ->join('reservas', 'reservas.id', '=', 'item_reservas.reserva_id')
            ->where('reservas.status', StatusConstant::RESERVADO->value);
    }
}
