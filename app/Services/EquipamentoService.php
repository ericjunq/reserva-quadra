<?php

namespace App\Services;

use App\Constants\StatusConstant;
use App\Models\Equipamento;
use App\Models\Espaco;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Ramsey\Collection\Collection;

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
        if (isset($data['quantidadeTotal'])) {
            $this->verificarNovaQuantidade($equipamento, $data['quantidadeTotal']);

            $equipamento->update($data);

            return $equipamento;
        }
    }

    public function desativar (Equipamento $equipamento):void
    {
        $equipamento->update(['ativo' => false]);
    }

    private function verificarNovaQuantidade(Equipamento $equipamento, int $novaQuantidde): void
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

        if ($novaQuantidde < $maiorUso)
        {
            throw new Exception(
                "Já existem {$maiorUso} unidades reservadas em um mesmo horário. A quantidade não pode ser menor que isso."
            );
        }
    }
}
