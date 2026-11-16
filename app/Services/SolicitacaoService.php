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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SolicitacaoService
{
    public function __construct(private TokenReservaService $tokens) {}

    public function aprovar(Reserva $reserva, int $equipamentoId, User $admin): Reserva
    {
        return $this->responder($reserva, $equipamentoId, $admin, SituacaoSolicitacaoConstant::APROVADA);
    }

    public function recusar(Reserva $reserva, int $equipamentoId, User $admin, string $justificativa): Reserva
    {
        return $this->responder($reserva, $equipamentoId, $admin, SituacaoSolicitacaoConstant::RECUSADA, $justificativa);
    }

    public function vencerPendentes(): int
    {
        $limite = now()->addHours((int) config('reservas.solicitacao_vence_horas_antes'));

        $itens = ItemReserva::query()
            ->with(['reserva.aluno', 'reserva.espaco', 'equipamento'])
            ->where('situacao_solicitacao', SituacaoSolicitacaoConstant::AGUARDANDO)
            ->whereHas('reserva', fn ($q) => $q
                ->where('status', StatusConstant::RESERVADO)
                ->where(fn ($w) => $w
                    ->where('data_reserva', '<', $limite->toDateString())
                    ->orWhere(fn ($dia) => $dia
                        ->where('data_reserva', $limite->toDateString())
                        ->where('hora_inicio', '<=', $limite->format('H:i:s')))))
            ->get();

        foreach ($itens as $item) {
            DB::transaction(function () use ($item) {
                $item->update(['situacao_solicitacao' => SituacaoSolicitacaoConstant::VENCIDA, 'respondida_em' => now()]);
                $this->avisar($item);
            });
        }

        return $itens->count();
    }

    private function responder(
        Reserva $reserva,
        int $equipamentoId,
        User $admin,
        SituacaoSolicitacaoConstant $resposta,
        ?string $justificativa = null,
    ): Reserva {
        DB::transaction(function () use ($reserva, $equipamentoId, $admin, $resposta, $justificativa) {
            $item = ItemReserva::query()
                ->with(['reserva.aluno', 'reserva.espaco', 'equipamento'])
                ->where('reserva_id', $reserva->id)
                ->where('equipamento_id', $equipamentoId)
                ->whereNotNull('situacao_solicitacao')
                ->lockForUpdate()
                ->first();

            if (! $item) {
                throw new RegraNegocioException('Não há pedido desse equipamento nesta reserva.', 404);
            }

            if ($item->reserva->status !== StatusConstant::RESERVADO) {
                throw new RegraNegocioException('Esta reserva não está mais ativa.', 409);
            }

            if ($item->situacao_solicitacao !== SituacaoSolicitacaoConstant::AGUARDANDO) {
                throw new RegraNegocioException('Este pedido já foi respondido.', 409);
            }

            $item->update([
                'situacao_solicitacao' => $resposta,
                'respondida_em'        => now(),
                'respondida_por'       => $admin->id,
                'justificativa_recusa' => $justificativa,
            ]);

            $this->avisar($item);
        });

        return $reserva->fresh(ReservaService::RELACOES_ADMIN);
    }

    private function avisar(ItemReserva $item): void
    {
        $reserva = $item->reserva;

        if (! $reserva->aluno) {
            return;
        }

        $linkCancelar = $item->situacao_solicitacao === SituacaoSolicitacaoConstant::APROVADA
            ? null
            : $this->tokens->link(
                FinalidadeTokenConstant::CANCELAR,
                $this->tokens->gerar($reserva, FinalidadeTokenConstant::CANCELAR, $reserva->inicio()),
            );

        Mail::to($reserva->aluno->email)->queue(
            (new SolicitacaoRespondidaMail($reserva, $item->equipamento->nome, $item->quantidade, $item->situacao_solicitacao, $item->justificativa_recusa, $linkCancelar))->afterCommit()
        );
    }
}
