<?php

namespace App\Services;

use App\Constants\FinalidadeTokenConstant;
use App\Constants\StatusConstant;
use App\Exceptions\RegraNegocioException;
use App\Mail\ReservaCanceladaMail;
use App\Models\Reserva;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CancelamentoService
{
    public function __construct(private TokenReservaService $tokens) {}

    public function resumo(string $token): Reserva
    {
        return $this->tokens
            ->buscarValido($token, FinalidadeTokenConstant::CANCELAR)
            ->reserva
            ->load('espaco', 'equipamentos');
    }

    public function podeCancelar(Reserva $reserva): bool
    {
        return $reserva->status === StatusConstant::RESERVADO && ! $reserva->iniciada();
    }

    public function cancelar(string $token): Reserva
    {
        return DB::transaction(function () use ($token) {
            $registro = $this->tokens->buscarValido($token, FinalidadeTokenConstant::CANCELAR, travar: true);
            $reserva = Reserva::whereKey($registro->reserva_id)->lockForUpdate()->firstOrFail();

            if ($reserva->status !== StatusConstant::RESERVADO) {
                throw new RegraNegocioException('Esta reserva não está mais ativa.', 409);
            }

            if ($reserva->iniciada()) {
                throw new RegraNegocioException('A reserva já começou e não pode mais ser cancelada.');
            }

            $reserva->update([
                'status'       => StatusConstant::CANCELADO,
                'cancelada_em' => now(),
            ]);

            $this->tokens->marcarComoUsado($registro);
            $this->avisarAluno($reserva);

            return $reserva->load('espaco', 'equipamentos');
        });
    }

    private function avisarAluno(Reserva $reserva): void
    {
        $aluno = $reserva->aluno()->first();

        if (! $aluno) {
            return;
        }

        Mail::to($aluno->email)->queue(
            (new ReservaCanceladaMail($reserva->load('espaco'), $aluno->nome_completo))->afterCommit()
        );
    }
}
