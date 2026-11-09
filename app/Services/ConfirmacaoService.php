<?php

namespace App\Services;

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

class ConfirmacaoService
{
    public function __construct(private TokenReservaService $tokens) {}

    public function limiteDaJanela(): Carbon
    {
        return today()->addWeekdays(config('reservas.confirmacao_dias_uteis_antes'));
    }

    public function dentroDaJanela(Reserva $reserva): bool
    {
        return $reserva->data_reserva->lte($this->limiteDaJanela());
    }

    public function prazo(Reserva $reserva): Carbon
    {
        return $reserva->data_reserva->copy()->subDay()->endOfDay();
    }

    public function dataDoPedido(Reserva $reserva): Carbon
    {
        return $reserva->data_reserva->copy()->subWeekdays(config('reservas.confirmacao_dias_uteis_antes'));
    }

    public function enviarPedido(Reserva $reserva, Aluno $aluno, bool $recemCriada = false): void
    {
        $confirmar = $this->tokens->gerar($reserva, FinalidadeTokenConstant::CONFIRMAR, $this->prazo($reserva));
        $cancelar = $this->tokens->gerar($reserva, FinalidadeTokenConstant::CANCELAR, $reserva->inicio());

        $reserva->update(['confirmacao_enviada_em' => now()]);

        Mail::to($aluno->email)->queue((new PedidoConfirmacaoMail(
            $reserva->load('espaco', 'equipamentos'),
            $aluno->nome_completo,
            $this->tokens->link(FinalidadeTokenConstant::CONFIRMAR, $confirmar),
            $this->tokens->link(FinalidadeTokenConstant::CANCELAR, $cancelar),
            $this->prazo($reserva),
            $recemCriada,
        ))->afterCommit());
    }

    public function pedirPendentes(): int
    {
        $reservas = Reserva::query()
            ->with('aluno')
            ->where('tipo', TipoReservaConstant::ALUNO)
            ->where('status', StatusConstant::RESERVADO)
            ->whereNull('confirmacao_enviada_em')
            ->whereNull('confirmada_em')
            ->where('data_reserva', '>', today()->toDateString())
            ->where('data_reserva', '<=', $this->limiteDaJanela()->toDateString())
            ->get();

        foreach ($reservas as $reserva) {
            DB::transaction(fn () => $this->enviarPedido($reserva, $reserva->aluno));
        }

        return $reservas->count();
    }

    public function resumo(string $token): Reserva
    {
        return $this->tokens
            ->buscarValido($token, FinalidadeTokenConstant::CONFIRMAR)
            ->reserva
            ->load('espaco', 'equipamentos');
    }

    public function podeConfirmar(Reserva $reserva): bool
    {
        return $reserva->status === StatusConstant::RESERVADO
            && $reserva->confirmada_em === null
            && $this->prazo($reserva)->isFuture();
    }

    public function confirmar(string $token): Reserva
    {
        return DB::transaction(function () use ($token) {
            $registro = $this->tokens->buscarValido($token, FinalidadeTokenConstant::CONFIRMAR, travar: true);
            $reserva = Reserva::whereKey($registro->reserva_id)->lockForUpdate()->firstOrFail();

            if ($reserva->status !== StatusConstant::RESERVADO) {
                throw new RegraNegocioException('Esta reserva não está mais ativa.', 409);
            }

            if ($reserva->confirmada_em !== null) {
                throw new RegraNegocioException('Esta reserva já foi confirmada.', 409);
            }

            $reserva->update(['confirmada_em' => now()]);
            $this->tokens->marcarComoUsado($registro);

            return $reserva->load('espaco', 'equipamentos');
        });
    }
}
