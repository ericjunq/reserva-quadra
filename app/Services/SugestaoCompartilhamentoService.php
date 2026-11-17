<?php

namespace App\Services;

use App\Constants\SituacaoSolicitacaoConstant;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SugestaoCompartilhamentoService
{
    public function listar(?int $equipamentoId = null): Collection
    {
        $minimo = config('reservas.sugestao_compartilhamento.minimo_pedidos');
        $desde = now()->subDays(config('reservas.sugestao_compartilhamento.dias'));

        $linhas = DB::table('item_reservas as i')
            ->join('reservas as r', 'r.id', '=', 'i.reserva_id')
            ->join('equipamentos as e', 'e.id', '=', 'i.equipamento_id')
            ->join('espacos as pede', 'pede.id', '=', 'r.espaco_id')
            ->leftJoin('sugestoes_dispensadas as d', fn ($join) => $join
                ->on('d.equipamento_id', '=', 'i.equipamento_id')
                ->on('d.espaco_id', '=', 'r.espaco_id'))
            ->where('i.situacao_solicitacao', SituacaoSolicitacaoConstant::APROVADA->value)
            ->where('i.respondida_em', '>=', $desde)
            ->where(fn ($q) => $q->whereNull('d.dispensada_em')->orWhereColumn('i.respondida_em', '>', 'd.dispensada_em'))
            ->where('e.ativo', true)
            ->where('pede.ativo', true)
            ->whereColumn('e.espaco_id', '!=', 'r.espaco_id')
            ->whereNotExists(fn ($q) => $q->from('equipamento_compartilhamentos as c')
                ->whereColumn('c.equipamento_id', 'i.equipamento_id')
                ->whereColumn('c.espaco_id', 'r.espaco_id'))
            ->when($equipamentoId, fn ($q, $id) => $q->where('i.equipamento_id', $id))
            ->groupBy('i.equipamento_id', 'r.espaco_id')
            ->havingRaw('COUNT(*) >= ?', [$minimo])
            ->select('i.equipamento_id', 'r.espaco_id', DB::raw('COUNT(*) as pedidos'), DB::raw('MAX(i.respondida_em) as ultimo_pedido_em'))
            ->get();

        if ($linhas->isEmpty()) {
            return collect();
        }

        $equipamentos = Equipamento::with('espaco:id,nome')->whereIn('id', $linhas->pluck('equipamento_id'))->get(['id', 'nome', 'espaco_id'])->keyBy('id');
        $espacos = Espaco::whereIn('id', $linhas->pluck('espaco_id'))->get(['id', 'nome'])->keyBy('id');

        return $linhas
            ->map(fn ($linha) => [
                'equipamento'      => $equipamentos[$linha->equipamento_id],
                'espaco'           => $espacos[$linha->espaco_id],
                'pedidos'          => (int) $linha->pedidos,
                'ultimo_pedido_em' => $linha->ultimo_pedido_em,
            ])
            ->sortBy([
                fn ($a, $b) => $b['pedidos'] <=> $a['pedidos'],
                fn ($a, $b) => strcmp($a['equipamento']->nome, $b['equipamento']->nome),
            ])
            ->values();
    }

    public function dispensar(Equipamento $equipamento, Espaco $espaco, User $admin): void
    {
        DB::table('sugestoes_dispensadas')->upsert(
            [[
                'equipamento_id' => $equipamento->id,
                'espaco_id'      => $espaco->id,
                'dispensada_em'  => now(),
                'dispensada_por' => $admin->id,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]],
            ['equipamento_id', 'espaco_id'],
            ['dispensada_em', 'dispensada_por', 'updated_at'],
        );
    }
}
