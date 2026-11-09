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

        return $this->intervalosEmUso($ids, $data, $data, $inicio, $fim)
            ->groupBy('equipamento_id')
            ->map(fn (Collection $linhas) => self::pico($linhas, $inicio, $fim));
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

        return $this->intervalosEmUso($ids, $de, $ate);
    }

    public function maiorUsoFuturo(Equipamento $equipamento): int
    {
        return (int) $this->intervalosEmUso([$equipamento->id], today()->toDateString())
            ->groupBy('data')
            ->map(fn (Collection $linhas) => self::pico($linhas, '00:00:00', '24:00:00'))
            ->max();
    }

    public static function pico(Collection $linhas, string $inicio, string $fim): int
    {
        $eventos = [];

        foreach ($linhas as $linha) {
            $de = max($linha->hora_inicio, $inicio);
            $ate = min($linha->hora_fim, $fim);

            if ($de < $ate) {
                $eventos[] = [$de, $linha->total];
                $eventos[] = [$ate, -$linha->total];
            }
        }

        usort($eventos, fn (array $a, array $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        $atual = 0;
        $pico = 0;

        foreach ($eventos as [, $variacao]) {
            $atual += $variacao;
            $pico = max($pico, $atual);
        }

        return $pico;
    }

    private function intervalosEmUso(array $ids, string $de, ?string $ate = null, ?string $inicio = null, ?string $fim = null): Collection
    {
        return $this->reservasAtivas()
            ->whereIn('item_reservas.equipamento_id', $ids)
            ->where('reservas.data_reserva', '>=', $de)
            ->when($ate, fn ($q) => $q->where('reservas.data_reserva', '<=', $ate))
            ->when($inicio && $fim, fn ($q) => $q
                ->where('reservas.hora_inicio', '<', $fim)
                ->where('reservas.hora_fim', '>', $inicio))
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

    private function reservasAtivas(): Builder
    {
        return DB::table('item_reservas')
            ->join('reservas', 'reservas.id', '=', 'item_reservas.reserva_id')
            ->where('reservas.status', StatusConstant::RESERVADO->value);
    }
}
