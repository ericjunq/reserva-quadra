<?php

namespace App\Services;

use App\Constants\EtapaConfirmacaoConstant;
use App\Constants\FinalidadeTokenConstant;
use App\Constants\StatusConstant;
use App\Constants\TipoReservaConstant;
use App\Exceptions\RegraNegocioException;
use App\Mail\PedidoConfirmacaoMail;
use App\Models\Aluno;
use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ConfirmacaoService
{
    public function __construct(private TokenReservaService $tokens) {}

    public function prazoDaConfirmacao(Reserva $reserva): Carbon
    {
        $limite = $reserva->inicio()->isFuture() ? $reserva->inicio() : $reserva->fim();
        $minutos = $reserva->data_reserva->isToday()
            ? config('reservas.confirmacao_minutos_mesmo_dia')
            : config('reservas.confirmacao_horas') * 60;

        return now()->addMinutes($minutos)->min($limite);
    }

    public function prazoDaReconfirmacao(Reserva $reserva): Carbon
    {
        return $reserva->data_reserva->copy()->subDay()->startOfDay();
    }

    public function dataDoPedidoDeReconfirmacao(Reserva $reserva): Carbon
    {
        return $reserva->data_reserva->copy()->subWeekdays(config('reservas.reconfirmacao_dias_uteis_antes'));
    }

    public function pedirNaCriacao(Reserva $reserva, Aluno $aluno): void
    {
        $prazo = $this->prazoDaConfirmacao($reserva);
        $reserva->update(['confirmar_ate' => $prazo]);

        $this->enviar($reserva, $aluno, EtapaConfirmacaoConstant::CONFIRMACAO, $prazo);
    }

    public function pedirReconfirmacoes(): int
    {
        $reservas = Reserva::query()
            ->with('aluno')
            ->where('tipo', TipoReservaConstant::ALUNO)
            ->where('status', StatusConstant::RESERVADO)
            ->whereNotNull('confirmada_em')
            ->whereNull('reconfirmacao_pedida_em')
            ->where('data_reserva', '>', today()->addDay()->toDateString())
            ->where('data_reserva', '<=', today()->addWeekdays(config('reservas.reconfirmacao_dias_uteis_antes'))->toDateString())
            ->get()
            ->reject(fn (Reserva $reserva) => $this->reconfirmacaoDispensada($reserva));

        foreach ($reservas as $reserva) {
            DB::transaction(function () use ($reserva) {
                $prazo = $this->prazoDaReconfirmacao($reserva);
                $reserva->update(['reconfirmacao_pedida_em' => now(), 'reconfirmar_ate' => $prazo]);

                $this->enviar($reserva, $reserva->aluno, EtapaConfirmacaoConstant::RECONFIRMACAO, $prazo);
            });
        }

        return $reservas->count();
    }

    public function reconfirmacaoDispensada(Reserva $reserva): bool
    {
        $corte = $this->dataDoPedidoDeReconfirmacao($reserva)
            ->subWeekdays(config('reservas.reconfirmacao_dispensada_dias_uteis'));

        return $reserva->confirmada_em->gte($corte);
    }

    public function resumo(string $token): Reserva
    {
        return $this->tokens
            ->buscarValido($token, FinalidadeTokenConstant::CONFIRMAR)
            ->reserva
            ->load('espaco', 'equipamentos');
    }

    public function etapa(Reserva $reserva): EtapaConfirmacaoConstant
    {
        return $reserva->confirmada_em === null
            ? EtapaConfirmacaoConstant::CONFIRMACAO
            : EtapaConfirmacaoConstant::RECONFIRMACAO;
    }

    public function prazo(Reserva $reserva): ?Carbon
    {
        return $this->etapa($reserva) === EtapaConfirmacaoConstant::CONFIRMACAO
            ? $reserva->confirmar_ate
            : $reserva->reconfirmar_ate;
    }

    public function podeConfirmar(Reserva $reserva): bool
    {
        if ($reserva->status !== StatusConstant::RESERVADO) {
            return false;
        }

        return $this->etapa($reserva) === EtapaConfirmacaoConstant::CONFIRMACAO
            ? (bool) $reserva->confirmar_ate?->isFuture()
            : $reserva->reconfirmacao_pedida_em !== null
                && $reserva->reconfirmada_em === null
                && (bool) $reserva->reconfirmar_ate?->isFuture();
    }

    public function confirmar(string $token, bool $aceiteTermos): Reserva
    {
        return DB::transaction(function () use ($token, $aceiteTermos) {
            $registro = $this->tokens->buscarValido($token, FinalidadeTokenConstant::CONFIRMAR, travar: true);
            $reserva = Reserva::whereKey($registro->reserva_id)->lockForUpdate()->firstOrFail();

            if ($reserva->status !== StatusConstant::RESERVADO) {
                throw new RegraNegocioException('Esta reserva não está mais ativa.', 409);
            }

            if ($this->etapa($reserva) === EtapaConfirmacaoConstant::CONFIRMACAO) {
                $this->confirmarPrimeiraVez($reserva, $aceiteTermos);
            } else {
                $this->reconfirmar($reserva);
            }

            $this->tokens->marcarComoUsado($registro);

            return $reserva->load('espaco', 'equipamentos');
        });
    }

    private function confirmarPrimeiraVez(Reserva $reserva, bool $aceiteTermos): void
    {
        if (! $aceiteTermos) {
            throw ValidationException::withMessages([
                'aceite_termos' => 'Para confirmar, leia e aceite os termos de uso e o aviso de privacidade.',
            ]);
        }

        $reserva->update([
            'confirmada_em'     => now(),
            'termos_aceitos_em' => now(),
            'termos_versao'     => config('reservas.termos_versao'),
        ]);
    }

    private function reconfirmar(Reserva $reserva): void
    {
        if ($reserva->reconfirmacao_pedida_em === null || $reserva->reconfirmada_em !== null) {
            throw new RegraNegocioException('Esta reserva já foi confirmada.', 409);
        }

        $reserva->update(['reconfirmada_em' => now()]);
    }

    private function enviar(Reserva $reserva, Aluno $aluno, EtapaConfirmacaoConstant $etapa, Carbon $prazo): void
    {
        $confirmar = $this->tokens->gerar($reserva, FinalidadeTokenConstant::CONFIRMAR, $prazo);
        $cancelar = $this->tokens->gerar(
            $reserva,
            FinalidadeTokenConstant::CANCELAR,
            $reserva->inicio()->isFuture() ? $reserva->inicio() : $reserva->fim(),
        );

        Mail::to($aluno->email)->queue((new PedidoConfirmacaoMail(
            $reserva->load('espaco', 'equipamentos'),
            $aluno->nome_completo,
            $this->tokens->link(FinalidadeTokenConstant::CONFIRMAR, $confirmar),
            $this->tokens->link(FinalidadeTokenConstant::CANCELAR, $cancelar),
            $etapa === EtapaConfirmacaoConstant::RECONFIRMACAO ? $prazo->copy()->subMinute() : $prazo,
            $etapa,
            $etapa === EtapaConfirmacaoConstant::CONFIRMACAO
                && $reserva->data_reserva->gt(today()->addWeekdays(config('reservas.reconfirmacao_dias_uteis_antes'))),
        ))->afterCommit());
    }
}
