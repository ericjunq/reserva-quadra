<?php

namespace App\Constants;

use Carbon\Carbon;

enum PeriodoConstant: string
{
    case HOJE = 'HOJE';
    case SEMANA_ATUAL = 'SEMANA_ATUAL';
    case PROXIMA_SEMANA = 'PROXIMA_SEMANA';
    case MES_ATUAL = 'MES_ATUAL';
    case MES_ANTERIOR = 'MES_ANTERIOR';
    case ANO_ATUAL = 'ANO_ATUAL';

    public function intervalo(): array
    {
        $hoje = today();

        [$de, $ate] = match ($this) {
            self::HOJE           => [$hoje, $hoje],
            self::SEMANA_ATUAL   => [$hoje->copy()->startOfWeek(Carbon::MONDAY), $hoje->copy()->endOfWeek(Carbon::SUNDAY)],
            self::PROXIMA_SEMANA => [$hoje->copy()->addWeek()->startOfWeek(Carbon::MONDAY), $hoje->copy()->addWeek()->endOfWeek(Carbon::SUNDAY)],
            self::MES_ATUAL      => [$hoje->copy()->startOfMonth(), $hoje->copy()->endOfMonth()],
            self::MES_ANTERIOR   => [$hoje->copy()->subMonthNoOverflow()->startOfMonth(), $hoje->copy()->subMonthNoOverflow()->endOfMonth()],
            self::ANO_ATUAL      => [$hoje->copy()->startOfYear(), $hoje->copy()->endOfYear()],
        };

        return [$de->toDateString(), $ate->toDateString()];
    }
}
