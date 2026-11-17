<?php

namespace App\Services;

use App\Constants\SituacaoDevolucaoConstant;
use App\Constants\StatusConstant;
use App\Exceptions\RegraNegocioException;
use App\Models\ItemReserva;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RetiradaService
{
    public const LIMITE_FORA_AGORA = 50;

    public function registrar(Reserva $reserva, array $itens, User $admin): Reserva
    {
        return DB::transaction(function () use ($reserva, $itens, $admin) {
            $reserva = Reserva::whereKey($reserva->id)->lockForUpdate()->firstOrFail();

            $this->verificarReserva($reserva);

            $registrados = ItemReserva::where('reserva_id', $reserva->id)
                ->paraEntregar()
                ->get()
                ->keyBy('equipamento_id');

            $this->verificarItens($itens, $registrados);

            foreach ($itens as $dados) {
                $item = $registrados[$dados['equipamento_id']];
                $item->fill(['quantidade_retirada' => $dados['quantidade_retirada']]);

                if ($item->isDirty('quantidade_retirada') || $item->retirado_em === null) {
                    $item->fill(['retirado_em' => now(), 'retirado_por' => $admin->id])->save();
                }
            }

            return $reserva->load(ReservaService::RELACOES_ADMIN);
        });
    }

    public function foraAgora(): array
    {
        $consulta = ItemReserva::query()
            ->where('situacao_devolucao', SituacaoDevolucaoConstant::PENDENTE)
            ->whereNotNull('retirado_em')
            ->where('quantidade_retirada', '>', 0);

        $itens = (clone $consulta)
            ->with(['equipamento:id,nome', 'reserva:id,data_reserva,hora_inicio,hora_fim,espaco_id,aluno_id,tipo,descricao,responsavel_nome', 'reserva.espaco:id,nome', 'reserva.aluno:id,nome_completo'])
            ->orderBy('retirado_em')
            ->orderBy('id')
            ->limit(self::LIMITE_FORA_AGORA)
            ->get();

        return [
            'total' => $itens->count() < self::LIMITE_FORA_AGORA ? $itens->count() : $consulta->count(),
            'itens' => $itens,
        ];
    }

    private function verificarReserva(Reserva $reserva): void
    {
        if ($reserva->status !== StatusConstant::RESERVADO) {
            throw new RegraNegocioException('Só é possível registrar retirada de reservas ativas.', 409);
        }

        $abre = $reserva->inicio()->subMinutes(config('reservas.retirada_minutos_antes'));

        if (now()->lt($abre)) {
            throw new RegraNegocioException('A retirada só pode ser registrada a partir de '.$abre->format('d/m H:i').'.');
        }

        if (now()->gt($reserva->fim())) {
            throw new RegraNegocioException('A reserva já terminou. Registre a devolução.');
        }
    }

    private function verificarItens(array $itens, Collection $registrados): void
    {
        $invalidos = [];

        foreach ($itens as $dados) {
            $item = $registrados[$dados['equipamento_id']] ?? null;
            $retirada = (int) $dados['quantidade_retirada'];

            $motivo = match (true) {
                ! $item                       => 'O equipamento não faz parte desta reserva.',
                $retirada > $item->quantidade => "A quantidade retirada não pode passar da reservada ({$item->quantidade}).",
                $item->quantidade_devolvida !== null
                    && $retirada < $item->quantidade_devolvida => "Já foram registradas {$item->quantidade_devolvida} unidade(s) devolvidas.",
                default                                        => null,
            };

            if ($motivo) {
                $invalidos[] = ['equipamento_id' => $dados['equipamento_id'], 'motivo' => $motivo];
            }
        }

        if ($invalidos) {
            throw new RegraNegocioException('Alguns itens da retirada são inválidos.', 422, ['itens_invalidos' => $invalidos]);
        }
    }
}
