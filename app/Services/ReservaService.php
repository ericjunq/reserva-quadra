<?php

namespace App\Services;

use App\Constants\StatusConstant;
use App\Constants\TipoReservaConstant;
use App\Exceptions\RegraNegocioException;
use App\Mail\ReservaRevogadaMail;
use App\Models\Aluno;
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

            if ($this->buscarConflito($data)) {
                throw new RegraNegocioException('Já existe uma reserva para esse espaço nesse horário.', 409);
            }

            return $aluno->reservas()->create([
                'data'      => $data['data'],
                'horario'   => $data['horario'],
                'espaco_id' => $data['espaco_id'],
                'tipo'      => TipoReservaConstant::ALUNO,
            ]);
        });
    }

    /**
     * Cria uma reserva de evento da faculdade.
     * Se o horário estiver ocupado, só prossegue com uma justificativa:
     * a reserva conflitante é revogada (e o aluno avisado) na mesma transação.
     */
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

            return Reserva::create([
                'data'      => $data['data'],
                'horario'   => $data['horario'],
                'espaco_id' => $data['espaco_id'],
                'descricao' => $data['descricao'],
                'user_id'   => $admin->id,
                'tipo'      => TipoReservaConstant::EVENTO,
            ]);
        });
    }

    public function revogar(Reserva $reserva, User $admin, string $justificativa): Reserva
    {
        return DB::transaction(function () use ($reserva, $admin, $justificativa) {
            $reserva = Reserva::whereKey($reserva->id)->lockForUpdate()->firstOrFail();

            if ($reserva->status !== StatusConstant::RESERVADO) {
                throw new RegraNegocioException('Só é possível revogar reservas ativas.', 409);
            }

            if ($reserva->data->lt(today())) {
                throw new RegraNegocioException('Não é possível revogar uma reserva que já passou.');
            }

            $reserva->update([
                'status'                  => StatusConstant::REVOGADO,
                'justificativa_revogacao' => $justificativa,
                'revogada_em'             => now(),
                'revogada_por'            => $admin->id,
            ]);

            if ($reserva->aluno) {
                // Entra na fila só depois do commit: se a transação falhar, o aluno não é avisado.
                Mail::to($reserva->aluno->email)->queue(
                    (new ReservaRevogadaMail($reserva->load('aluno', 'espaco')))->afterCommit()
                );
            }

            return $reserva;
        });
    }

    /**
     * Trava a linha do espaço até o fim da transação. Assim, reservas
     * simultâneas no mesmo espaço são processadas uma de cada vez — o lock
     * nas reservas não basta, porque quando ainda não há reserva não há linha para travar.
     */
    private function travarEspaco(int $espacoId): void
    {
        Espaco::whereKey($espacoId)->lockForUpdate()->firstOrFail();
    }

    private function buscarConflito(array $data): ?Reserva
    {
        return Reserva::where('espaco_id', $data['espaco_id'])
            ->whereDate('data', $data['data'])
            ->where('horario', $data['horario'])
            ->where('status', StatusConstant::RESERVADO)
            ->first();
    }
}
