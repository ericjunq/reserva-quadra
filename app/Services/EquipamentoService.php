<?php

namespace App\Services;

use App\Constants\StatusConstant;
use App\Exceptions\RegraNegocioException;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Repositories\DisponibilidadeEquipamentoRepository;
use App\Support\VersaoDisponibilidade;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EquipamentoService
{
    public function __construct(private DisponibilidadeEquipamentoRepository $disponibilidade) {}

    public function listarAtivos(Espaco $espaco): Collection
    {
        return Equipamento::query()
            ->where('ativo', true)
            ->usavelNoEspaco($espaco->id)
            ->orderBy('nome')
            ->get();
    }

    public function disponibilidadeNoIntervalo(Espaco $espaco, string $data, string $inicio, string $fim): array
    {
        $equipamentos = $this->listarAtivos($espaco);
        $disponiveis = $this->disponibilidade->disponiveis($equipamentos, $data, $inicio, $fim);

        return $equipamentos->map(fn (Equipamento $equipamento) => [
            'id'         => $equipamento->id,
            'nome'       => $equipamento->nome,
            'total'      => $equipamento->quantidade_total,
            'disponivel' => $disponiveis[$equipamento->id],
        ])->values()->all();
    }

    public function listarPaginado(
        int $porPagina = 15,
        ?int $espacoId = null,
        ?string $nome = null,
        ?bool $ativo = null,
    ): LengthAwarePaginator {
        return Equipamento::with(Equipamento::RELACOES_ADMIN)
            ->when($espacoId, fn ($q) => $q->where('espaco_id', $espacoId))
            ->when($nome, fn ($q) => $q->whereLike('nome', "%{$nome}%", caseSensitive: false))
            ->when(! is_null($ativo), fn ($q) => $q->where('ativo', $ativo))
            ->orderBy('nome')
            ->paginate($porPagina);
    }

    public function criar(array $data): Equipamento
    {
        return DB::transaction(function () use ($data) {
            $equipamento = Equipamento::create(collect($data)->except('espacos_compartilhados')->all());

            if (isset($data['espacos_compartilhados'])) {
                $this->compartilhar($equipamento, $data['espacos_compartilhados']);
            }

            return $equipamento;
        });
    }

    public function atualizar(Equipamento $equipamento, array $data): Equipamento
    {
        return DB::transaction(function () use ($equipamento, $data) {
            if (isset($data['quantidade_total'])) {
                Equipamento::whereKey($equipamento->id)->lockForUpdate()->first();
                $this->verificarNovaQuantidade($equipamento, $data['quantidade_total']);
            }

            $equipamento->update(collect($data)->except('espacos_compartilhados')->all());

            if (array_key_exists('espacos_compartilhados', $data)) {
                $this->compartilhar($equipamento, $data['espacos_compartilhados']);
            }

            return $equipamento;
        });
    }

    public function desativar(Equipamento $equipamento): void
    {
        $equipamento->update(['ativo' => false]);
    }

    private function compartilhar(Equipamento $equipamento, array $espacoIds): void
    {
        $antes = $equipamento->espacosCompartilhados()->orderBy('espacos.id')->pluck('espacos.id')->all();
        $depois = collect($espacoIds)->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();

        if ($antes === $depois) {
            return;
        }

        $this->verificarRemocaoDoCompartilhamento($equipamento, array_values(array_diff($antes, $depois)));

        $equipamento->espacosCompartilhados()->sync($depois);
        $equipamento->auditarAlteracao(['espacos_compartilhados' => $antes], ['espacos_compartilhados' => $depois]);
        DB::afterCommit(fn () => VersaoDisponibilidade::avancar());
    }

    private function verificarRemocaoDoCompartilhamento(Equipamento $equipamento, array $removidos): void
    {
        if (! $removidos) {
            return;
        }

        $emUso = DB::table('item_reservas')
            ->join('reservas', 'reservas.id', '=', 'item_reservas.reserva_id')
            ->where('item_reservas.equipamento_id', $equipamento->id)
            ->whereIn('reservas.espaco_id', $removidos)
            ->where('reservas.status', StatusConstant::RESERVADO->value)
            ->where('reservas.data_reserva', '>=', today()->toDateString())
            ->distinct()
            ->pluck('reservas.espaco_id');

        if ($emUso->isNotEmpty()) {
            $nomes = Espaco::whereIn('id', $emUso)->orderBy('nome')->pluck('nome');

            throw new RegraNegocioException(
                "O equipamento tem reservas futuras em {$nomes->join(', ', ' e ')}. Ele só pode deixar de ser compartilhado com esses espaços depois delas.",
                409,
                ['espacos' => $emUso->values()->all()],
            );
        }
    }

    private function verificarNovaQuantidade(Equipamento $equipamento, int $novaQuantidade): void
    {
        $maiorUso = $this->disponibilidade->maiorUsoFuturo($equipamento);

        if ($novaQuantidade < $maiorUso) {
            throw new RegraNegocioException(
                "Já existem {$maiorUso} unidades reservadas em um mesmo horário. A quantidade não pode ser menor que isso.",
                409,
            );
        }
    }
}
