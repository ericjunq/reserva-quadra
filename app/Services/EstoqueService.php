<?php

namespace App\Services;

use App\Constants\TipoMovimentacaoConstant;
use App\Exceptions\RegraNegocioException;
use App\Models\Equipamento;
use App\Models\ItemReserva;
use App\Models\MovimentacaoEstoque;
use App\Repositories\DisponibilidadeEquipamentoRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EstoqueService
{
    public const ESTADO_DO_CADASTRO = 'Cadastro do equipamento.';

    public function __construct(private DisponibilidadeEquipamentoRepository $disponibilidade) {}

    public function listarPaginado(Equipamento $equipamento, int $porPagina): LengthAwarePaginator
    {
        return $equipamento->movimentacoes()
            ->with(['usuario:id,name', 'reserva:id,tipo,data_reserva,hora_inicio,hora_fim'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($porPagina);
    }

    public function registrarCadastro(Equipamento $equipamento): MovimentacaoEstoque
    {
        return $this->gravar($equipamento, TipoMovimentacaoConstant::ENTRADA, $equipamento->quantidade_total, self::ESTADO_DO_CADASTRO, null);
    }

    public function movimentar(Equipamento $equipamento, array $dados): MovimentacaoEstoque
    {
        $tipo = TipoMovimentacaoConstant::from($dados['tipo']);
        $quantidade = (int) $dados['quantidade'];
        $daManutencao = (bool) ($dados['da_manutencao'] ?? false);

        return DB::transaction(function () use ($equipamento, $tipo, $quantidade, $daManutencao, $dados) {
            $atual = Equipamento::whereKey($equipamento->id)->lockForUpdate()->firstOrFail();

            if ($tipo->exigeReserva()) {
                $this->verificarReserva($atual, (int) $dados['reserva_id']);
            }

            [$total, $manutencao] = $this->calcular($atual, $tipo, $quantidade, $daManutencao);
            $this->verificar($atual, $total, $manutencao);

            $atual->update(['quantidade_total' => $total, 'quantidade_em_manutencao' => $manutencao]);

            return $this->gravar(
                $atual,
                $tipo,
                $tipo === TipoMovimentacaoConstant::AJUSTE ? $quantidade : abs($quantidade),
                $dados['estado'],
                $tipo->exigeReserva() || isset($dados['reserva_id']) ? (int) $dados['reserva_id'] : null,
            );
        });
    }

    public function verificarUsoFuturo(Equipamento $equipamento, int $utilizavel): void
    {
        $maiorUso = $this->disponibilidade->maiorUsoFuturo($equipamento);

        if ($utilizavel < $maiorUso) {
            throw new RegraNegocioException(
                "Já existem {$maiorUso} unidade(s) reservada(s) num mesmo horário a partir de agora. O estoque utilizável não pode ficar abaixo disso.",
                409,
                ['maior_uso_futuro' => $maiorUso],
            );
        }
    }

    private function calcular(Equipamento $atual, TipoMovimentacaoConstant $tipo, int $quantidade, bool $daManutencao): array
    {
        $total = $atual->quantidade_total;
        $manutencao = $atual->quantidade_em_manutencao;

        return match ($tipo) {
            TipoMovimentacaoConstant::ENTRADA                                        => [$total + $quantidade, $manutencao],
            TipoMovimentacaoConstant::BAIXA, TipoMovimentacaoConstant::NAO_DEVOLVIDO => [
                $total - $quantidade,
                $daManutencao ? $manutencao - $quantidade : $manutencao,
            ],
            TipoMovimentacaoConstant::MANUTENCAO => [$total, $manutencao + $quantidade],
            TipoMovimentacaoConstant::RETORNO    => [$total, $manutencao - $quantidade],
            TipoMovimentacaoConstant::AJUSTE     => [$total + $quantidade, $manutencao],
        };
    }

    private function verificar(Equipamento $atual, int $total, int $manutencao): void
    {
        if ($manutencao < 0) {
            throw new RegraNegocioException("Só há {$atual->quantidade_em_manutencao} unidade(s) em manutenção.", 422);
        }

        if ($total < 0 || $manutencao > $total) {
            throw new RegraNegocioException(
                "O estoque tem {$atual->quantidade_total} unidade(s), {$atual->quantidade_em_manutencao} em manutenção. A quantidade informada passa disso.",
                422,
            );
        }

        if ($total - $manutencao < $atual->utilizavel()) {
            $this->verificarUsoFuturo($atual, $total - $manutencao);
        }
    }

    private function verificarReserva(Equipamento $equipamento, int $reservaId): void
    {
        $existe = ItemReserva::query()
            ->where('reserva_id', $reservaId)
            ->where('equipamento_id', $equipamento->id)
            ->exists();

        if (! $existe) {
            throw new RegraNegocioException('Este equipamento não faz parte da reserva informada.', 422);
        }
    }

    private function gravar(Equipamento $equipamento, TipoMovimentacaoConstant $tipo, int $quantidade, string $estado, ?int $reservaId): MovimentacaoEstoque
    {
        return MovimentacaoEstoque::create([
            'equipamento_id'          => $equipamento->id,
            'tipo'                    => $tipo,
            'quantidade'              => $quantidade,
            'estado'                  => $estado,
            'reserva_id'              => $reservaId,
            'user_id'                 => Auth::id(),
            'quantidade_total_depois' => $equipamento->quantidade_total,
            'em_manutencao_depois'    => $equipamento->quantidade_em_manutencao,
        ]);
    }
}
