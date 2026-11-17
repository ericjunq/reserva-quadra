<?php

namespace App\Services;

use App\Constants\FinalidadeTokenConstant;
use App\Constants\SituacaoSolicitacaoConstant;
use App\Constants\StatusConstant;
use App\Exceptions\RegraNegocioException;
use App\Mail\SolicitacaoRespondidaMail;
use App\Models\ItemReserva;
use App\Models\Reserva;
use App\Models\User;
use App\Support\VersaoDisponibilidade;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SolicitacaoService
{
    public const SEM_TEMPO = 'Faltam poucas horas para a reserva: peça o equipamento diretamente na coordenação.';

    public const JUSTIFICATIVA_NAO_MANTIDA = 'Equipamento não liberado e a reserva não foi mantida.';

    public function __construct(private TokenReservaService $tokens) {}

    public function prazoDeResposta(CarbonInterface $inicio): ?Carbon
    {
        $minimo = now()->addHours((int) config('reservas.solicitacao_tempo_minimo_resposta_horas'));

        foreach (['solicitacao_prazo_horas_antes', 'solicitacao_prazo_curto_horas_antes'] as $chave) {
            $prazo = Carbon::parse($inicio)->subHours((int) config("reservas.{$chave}"));

            if ($prazo->gte($minimo)) {
                return $prazo;
            }
        }

        return null;
    }

    public function aprovar(Reserva $reserva, ?int $equipamentoId, User $admin): Reserva
    {
        return $this->responder($reserva, $equipamentoId, $admin, SituacaoSolicitacaoConstant::APROVADA);
    }

    public function recusar(Reserva $reserva, ?int $equipamentoId, User $admin, string $justificativa): Reserva
    {
        return $this->responder($reserva, $equipamentoId, $admin, SituacaoSolicitacaoConstant::RECUSADA, $justificativa);
    }

    public function vencerPendentes(): int
    {
        $reservaIds = ItemReserva::query()
            ->where('situacao_solicitacao', SituacaoSolicitacaoConstant::AGUARDANDO)
            ->where('responder_ate', '<=', now())
            ->whereHas('reserva', fn ($q) => $q->where('status', StatusConstant::RESERVADO))
            ->distinct()
            ->pluck('reserva_id');

        $vencidos = 0;

        foreach ($reservaIds as $reservaId) {
            DB::transaction(function () use ($reservaId, &$vencidos) {
                $vencidos += ItemReserva::query()
                    ->where('reserva_id', $reservaId)
                    ->where('situacao_solicitacao', SituacaoSolicitacaoConstant::AGUARDANDO)
                    ->where('responder_ate', '<=', now())
                    ->update(['situacao_solicitacao' => SituacaoSolicitacaoConstant::VENCIDA, 'respondida_em' => now()]);

                $this->avisarSeConcluido(Reserva::findOrFail($reservaId));
            });
        }

        VersaoDisponibilidade::avancar();

        return $vencidos;
    }

    public function resumoParaManter(string $token): Reserva
    {
        return $this->tokens
            ->buscarValido($token, FinalidadeTokenConstant::MANTER)
            ->reserva
            ->load('espaco', 'equipamentos');
    }

    public function podeManter(Reserva $reserva): bool
    {
        return $reserva->status === StatusConstant::RESERVADO
            && $reserva->mantida_em === null
            && (bool) $reserva->manter_ate?->isFuture();
    }

    public function manter(string $token): Reserva
    {
        return DB::transaction(function () use ($token) {
            $registro = $this->tokens->buscarValido($token, FinalidadeTokenConstant::MANTER, travar: true);
            $reserva = Reserva::whereKey($registro->reserva_id)->lockForUpdate()->firstOrFail();

            if (! $this->podeManter($reserva)) {
                throw new RegraNegocioException('Esta reserva não precisa mais ser mantida ou o prazo terminou.', 409);
            }

            $reserva->update(['mantida_em' => now()]);
            $this->tokens->marcarComoUsado($registro);

            return $reserva->load('espaco', 'equipamentos');
        });
    }

    private function responder(
        Reserva $reserva,
        ?int $equipamentoId,
        User $admin,
        SituacaoSolicitacaoConstant $resposta,
        ?string $justificativa = null,
    ): Reserva {
        DB::transaction(function () use ($reserva, $equipamentoId, $admin, $resposta, $justificativa) {
            $atual = Reserva::whereKey($reserva->id)->lockForUpdate()->firstOrFail();

            $itens = ItemReserva::query()
                ->where('reserva_id', $reserva->id)
                ->whereNotNull('situacao_solicitacao')
                ->when($equipamentoId, fn ($q) => $q->where('equipamento_id', $equipamentoId))
                ->lockForUpdate()
                ->get();

            if ($itens->isEmpty()) {
                throw new RegraNegocioException('Não há pedido desse equipamento nesta reserva.', 404);
            }

            if ($atual->status !== StatusConstant::RESERVADO) {
                throw new RegraNegocioException('Esta reserva não está mais ativa.', 409);
            }

            $pendentes = $itens->where('situacao_solicitacao', SituacaoSolicitacaoConstant::AGUARDANDO);

            if ($pendentes->isEmpty()) {
                throw new RegraNegocioException($equipamentoId ? 'Este pedido já foi respondido.' : 'Não há pedidos aguardando resposta.', 409);
            }

            foreach ($pendentes as $item) {
                $item->update([
                    'situacao_solicitacao' => $resposta,
                    'respondida_em'        => now(),
                    'respondida_por'       => $admin->id,
                    'justificativa_recusa' => $justificativa,
                ]);
            }

            $this->avisarSeConcluido($atual);
        });

        return $reserva->fresh(ReservaService::RELACOES_ADMIN);
    }

    private function avisarSeConcluido(Reserva $reserva): void
    {
        $reserva->load(['aluno', 'espaco', 'equipamentos']);
        $pedidos = $reserva->equipamentos->filter(fn ($e) => $e->pivot->situacao_solicitacao !== null);

        if (! $reserva->aluno || $pedidos->isEmpty()
            || $pedidos->contains(fn ($e) => $e->pivot->situacao_solicitacao === SituacaoSolicitacaoConstant::AGUARDANDO)) {
            return;
        }

        $itens = $pedidos->map(fn ($e) => [
            'nome'          => $e->nome,
            'quantidade'    => $e->pivot->quantidade,
            'situacao'      => $e->pivot->situacao_solicitacao,
            'justificativa' => $e->pivot->justificativa_recusa,
            'obrigatorio'   => (bool) $e->pivot->solicitacao_obrigatoria,
        ])->values()->all();

        $naoLiberados = $pedidos->contains(fn ($e) => $e->pivot->situacao_solicitacao !== SituacaoSolicitacaoConstant::APROVADA);
        $manterAte = $naoLiberados ? $this->pedirParaManter($reserva) : null;

        Mail::to($reserva->aluno->email)->queue((new SolicitacaoRespondidaMail(
            $reserva,
            $itens,
            $naoLiberados,
            $manterAte,
            $manterAte ? $this->link($reserva, FinalidadeTokenConstant::MANTER, $manterAte) : null,
            $naoLiberados ? $this->link($reserva, FinalidadeTokenConstant::CANCELAR, $reserva->inicio()) : null,
        ))->afterCommit());
    }

    private function pedirParaManter(Reserva $reserva): ?Carbon
    {
        if ($reserva->manter_ate !== null || $reserva->mantida_em !== null) {
            return null;
        }

        $ultimo = $reserva->inicio()->subHours((int) config('reservas.manter_reserva_minimo_horas_antes'));

        if ($ultimo->lte(now())) {
            return null;
        }

        $prazo = now()->addHours((int) config('reservas.manter_reserva_horas'))->min($ultimo);
        $reserva->update(['manter_ate' => $prazo]);

        return $prazo;
    }

    private function link(Reserva $reserva, FinalidadeTokenConstant $finalidade, CarbonInterface $expiraEm): string
    {
        return $this->tokens->link($finalidade, $this->tokens->gerar($reserva, $finalidade, $expiraEm));
    }
}
