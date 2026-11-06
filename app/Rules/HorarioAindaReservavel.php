<?php

namespace App\Rules;

use App\Constants\HorarioReservaConstant;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class HorarioAindaReservavel implements DataAwareRule, ValidationRule
{
    private array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public static function limiteParaHoje(HorarioReservaConstant $horario): Carbon
    {
        return Carbon::parse(today()->toDateString()." {$horario->fim()}")
            ->subMinutes(config('reservas.minutos_antes_do_fim'));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $horario = HorarioReservaConstant::tryFrom((string) $value);
        $dataReserva = $this->data['data_reserva'] ?? null;

        if (! $horario || ! is_string($dataReserva) || $dataReserva !== today()->toDateString()) {
            return;
        }

        $minutos = config('reservas.minutos_antes_do_fim');
        $limite = self::limiteParaHoje($horario);

        if (now()->gt($limite)) {
            $fail("Esse horário não pode mais ser reservado hoje. Reservas para hoje são aceitas até {$minutos} minutos antes do término ({$limite->format('H:i')}).");
        }
    }
}
