<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Exceptions\RegraNegocioException;
use App\Repositories\DisponibilidadeEquipamentoRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

class EquipamentoService
{
    public function __construct(private DisponibilidadeEquipamentoRepository $disponibilidade) {}

    public function listarAtivos(Espaco $espaco): Collection
    {
        return Equipamento::query()
        ->where('ativo', true)
        ->where(fn ($q) => $q->where('espaco_id', $espaco->id)->orWhere('compartilhavel', true))
        ->orderBy('nome')
        ->get();
    }

    public function listarPaginado(
        int $porPagina = 15,
        ?int $espacoId = null,
        ?string $nome = null,
        ?bool $ativo = null,
    ): LengthAwarePaginator {
        return Equipamento::with(Auditoria::RELACOES)
        ->when($espacoId, fn ($q) => $q->where('espaco_id', $espacoId))
        ->when($nome, fn ($q) => $q->where('nome', 'like', "%{$nome}%"))
        ->when(! is_null($ativo), fn ($q) => $q->where('ativo', $ativo))
        ->orderBy('nome')
        ->paginate($porPagina);
    }

    public function criar(array $data): Equipamento
    {
        return Equipamento::create($data);
    }

    public function atualizar(Equipamento $equipamento, array $data): Equipamento
    {
        return DB::transaction(function () use ($equipamento, $data) {
            if (isset($data['quantidade_total'])) {
                Equipamento::whereKey($equipamento->id)->lockForUpdate()->first();
                $this->verificarNovaQuantidade($equipamento, $data['quantidade_total']);
            }

            $equipamento->update($data);

            return $equipamento;
        });
    }

    public function desativar (Equipamento $equipamento):void
    {
        $equipamento->update(['ativo' => false]);
    }

    private function verificarNovaQuantidade(Equipamento $equipamento, int $novaQuantidade): void
    {
        $maiorUso = $this->disponibilidade->maiorUsoFuturo($equipamento);

        if ($novaQuantidade < $maiorUso)
        {
            throw new RegraNegocioException(
                "Já existem {$maiorUso} unidades reservadas em um mesmo horário. A quantidade não pode ser menor que isso.",
                409,
            );
        }
    }
}
