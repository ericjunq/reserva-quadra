<?php

namespace App\Services;

use App\Constants\SituacaoDevolucaoConstant;
use App\Constants\StatusConstant;
use App\Exceptions\RegraNegocioException;
use App\Mail\DevolucaoComPendenciaMail;
use App\Models\ItemReserva;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class DevolucaoService
{
    public function registrar(Reserva $reserva, array $itens, User $admin): Reserva
    {
        return DB::transaction(function () use ($reserva, $itens, $admin) {
            $reserva = Reserva::whereKey($reserva->id)->lockForUpdate()->firstOrFail();

            $this->verificarReserva($reserva);

            $registrados = ItemReserva::where('reserva_id', $reserva->id)
                ->with('equipamento')
                ->get()
                ->keyBy('equipamento_id');

            $this->verificarItens($itens, $registrados);

            $comPendencia = collect();

            foreach ($itens as $dados) {
                $item = $registrados[$dados['equipamento_id']];
                $situacao = SituacaoDevolucaoConstant::from($dados['situacao']);

                $item->update([
                    'situacao_devolucao'   => $situacao,
                    'quantidade_devolvida' => $dados['quantidade_devolvida'],
                    'observacao_devolucao' => $dados['observacao'] ?? null,
                    'devolvido_em'         => now(),
                    'devolvido_por'        => $admin->id,
                ]);

                if ($situacao->temPendencia() && $item->wasChanged(['situacao_devolucao', 'quantidade_devolvida', 'observacao_devolucao'])) {
                    $comPendencia->push($item);
                }
            }

            $this->avisarAluno($reserva, $comPendencia);

            return $reserva->load(ReservaService::RELACOES_ADMIN);
        });
    }

    private function verificarReserva(Reserva $reserva): void
    {
        if ($reserva->status !== StatusConstant::RESERVADO) {
            throw new RegraNegocioException('Só é possível registrar devolução de reservas ativas.', 409);
        }

        if (! $reserva->iniciada()) {
            throw new RegraNegocioException('A devolução só pode ser registrada depois do início da reserva.');
        }
    }

    private function verificarItens(array $itens, Collection $registrados): void
    {
        $invalidos = [];

        foreach ($itens as $dados) {
            $item = $registrados[$dados['equipamento_id']] ?? null;

            if (! $item) {
                $invalidos[] = ['equipamento_id' => $dados['equipamento_id'], 'motivo' => 'O equipamento não faz parte desta reserva.'];

                continue;
            }

            $motivo = $this->incoerencia(
                SituacaoDevolucaoConstant::from($dados['situacao']),
                (int) $dados['quantidade_devolvida'],
                $item->quantidade,
            );

            if ($motivo) {
                $invalidos[] = ['equipamento_id' => $item->equipamento_id, 'motivo' => $motivo];
            }
        }

        if ($invalidos) {
            throw new RegraNegocioException(
                'Alguns itens da devolução são inválidos.',
                422,
                ['itens_invalidos' => $invalidos],
            );
        }
    }

    private function incoerencia(SituacaoDevolucaoConstant $situacao, int $devolvida, int $reservada): ?string
    {
        return match (true) {
            $devolvida > $reservada                                                            => "A quantidade devolvida não pode passar da reservada ({$reservada}).",
            $situacao === SituacaoDevolucaoConstant::DEVOLVIDO && $devolvida !== $reservada    => "Devolvido exige a quantidade inteira ({$reservada}).",
            $situacao === SituacaoDevolucaoConstant::DEVOLVIDO_COM_AVARIA && $devolvida < 1    => 'Devolvido com avaria exige ao menos 1 unidade devolvida.',
            $situacao === SituacaoDevolucaoConstant::NAO_DEVOLVIDO && $devolvida >= $reservada => 'Não devolvido exige quantidade devolvida menor que a reservada.',
            default                                                                            => null,
        };
    }

    private function avisarAluno(Reserva $reserva, Collection $itens): void
    {
        if ($itens->isEmpty() || ! $reserva->aluno) {
            return;
        }

        $resumo = $itens->map(fn (ItemReserva $item) => [
            'nome'                 => $item->equipamento->nome,
            'quantidade'           => $item->quantidade,
            'quantidade_devolvida' => $item->quantidade_devolvida,
            'situacao'             => $item->situacao_devolucao->label(),
            'observacao'           => $item->observacao_devolucao,
        ])->values()->all();

        Mail::to($reserva->aluno->email)->queue(
            (new DevolucaoComPendenciaMail($reserva->load('aluno', 'espaco'), $resumo))->afterCommit()
        );
    }
}
