<?php

namespace App\Services;

use App\Constants\StatusConstant;
use App\Constants\TipoReservaConstant;
use App\Exceptions\RegraNegocioException;
use App\Mail\ReservaRevogadaMail;
use App\Models\Aluno;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ReservaService
{
    public function create(array $data): Reserva
    {
        $aluno = Aluno::where('registro_academico', $data['registro_academico'])->firstOrFail();

        return DB::transaction(function () use ($aluno, $data) {
            $this->travarEspaco($data['espaco_id']);
            $this->verificarConflitoDeEspaco($data);
            $this->verificarDisponibilidadeEquipamentos($data);

            $reserva = $aluno->reservas()->create([
                'data_reserva' => $data['data_reserva'],
                'horario'   => $data['horario'],
                'espaco_id' => $data['espaco_id'],
                'tipo'      => TipoReservaConstant::ALUNO,
            ]);

            $this->anexarEquipamentos($reserva, $data);

            return $reserva->load('equipamentos');
        });
    }

    public function criarEvento(array $data, User $admin): Reserva
    {
        return DB::transaction(function () use ($data, $admin) {
            $this->travarEspaco($data['espaco_id']);

            if ($conflito = $this->buscarConflito($data)) {
                if (empty($data['justificativa'])) {
                    throw new RegraNegocioException(
                        'Já existe uma reserva nesse horário. Envie uma justificativa para revogá-la e criar o evento.',
                        409,
                        ['reserva_conflitante_id' => $conflito->id],
                    );
                }

                $this->revogar($conflito, $admin, $data['justificativa']);
            }

            $this->verificarDisponibilidadeEquipamentos($data);

            $reserva = Reserva::create([
                'data_reserva' => $data['data_reserva'],
                'horario'   => $data['horario'],
                'espaco_id' => $data['espaco_id'],
                'descricao' => $data['descricao'],
                'user_id'   => $admin->id,
                'tipo'      => TipoReservaConstant::EVENTO,
            ]);

            $this->anexarEquipamentos($reserva, $data);

            return $reserva->load('equipamentos');
        });
    }

    public function revogar(Reserva $reserva, User $admin, string $justificativa): Reserva
    {
        return DB::transaction(function () use ($reserva, $admin, $justificativa) {
            $reserva = Reserva::whereKey($reserva->id)->lockForUpdate()->firstOrFail();

            if ($reserva->status !== StatusConstant::RESERVADO) {
                throw new RegraNegocioException('Só é possível revogar reservas ativas.', 409);
            }

            if ($reserva->data_reserva->lt(today())) {
                throw new RegraNegocioException('Não é possível revogar uma reserva que já passou.');
            }

            $reserva->update([
                'status'                  => StatusConstant::REVOGADO,
                'justificativa_revogacao' => $justificativa,
                'revogada_em'             => now(),
                'revogada_por'            => $admin->id,
            ]);

            if ($reserva->aluno) {
                Mail::to($reserva->aluno->email)->queue(
                    (new ReservaRevogadaMail($reserva->load('aluno', 'espaco')))->afterCommit()
                );
            }

            return $reserva;
        });
    }

    private function travarEspaco(int $espacoId): void
    {
        Espaco::whereKey($espacoId)->lockForUpdate()->firstOrFail();
    }

    private function verificarConflitoDeEspaco(array $data): void
    {
        if ($this->buscarConflito($data)) {
            throw new RegraNegocioException('Já existe uma reserva para esse espaço nesse horário.', 409);
        }
    }

    private function verificarDisponibilidadeEquipamentos(array $data): void
    {
        $itens = $data['equipamentos'] ?? [];

        if (! $itens) {
            return;
        }

        $ids = array_column($itens, 'id');

        $equipamentos = Equipamento::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        $emUso = DB::table('item_reservas')
            ->join('reservas', 'reservas.id', '=', 'item_reservas.reserva_id')
            ->whereIn('item_reservas.equipamento_id', $ids)
            ->whereDate('reservas.data_reserva', $data['data_reserva'])
            ->where('reservas.horario', $data['horario'])
            ->where('reservas.status', StatusConstant::RESERVADO->value)
            ->groupBy('item_reservas.equipamento_id')
            ->selectRaw('item_reservas.equipamento_id, SUM(item_reservas.quantidade) as total')
            ->pluck('total', 'equipamento_id');

        $indisponiveis = [];

        foreach ($itens as $item) {
            $equipamento = $equipamentos[$item['id']];
            $disponivel = $equipamento->quantidade_total - (int) ($emUso[$item['id']] ?? 0);

            if ($item['quantidade'] > $disponivel) {
                $indisponiveis[] = [
                    'id'          => $equipamento->id,
                    'nome'        => $equipamento->nome,
                    'solicitado'  => $item['quantidade'],
                    'disponivel'  => max($disponivel, 0),
                ];
            }
        }

        if ($indisponiveis) {
            throw new RegraNegocioException(
                'Alguns equipamentos não estão disponíveis nesse horário.',
                409,
                ['equipamentos_indisponiveis' => $indisponiveis],
            );
        }
    }

    private function anexarEquipamentos(Reserva $reserva, array $data): void
    {
        $itens = collect($data['equipamentos'] ?? [])
            ->mapWithKeys(fn ($item) => [$item['id'] => ['quantidade' => $item['quantidade']]]);

        if ($itens->isNotEmpty()) {
            $reserva->equipamentos()->attach($itens->all());
        }
    }

    private function buscarConflito(array $data): ?Reserva
    {
        return Reserva::where('espaco_id', $data['espaco_id'])
            ->whereDate('data_reserva', $data['data_reserva'])
            ->where('horario', $data['horario'])
            ->where('status', StatusConstant::RESERVADO)
            ->first();
    }
}
