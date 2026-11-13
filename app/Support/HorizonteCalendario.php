<?php

namespace App\Support;

use App\Rules\AntecedenciaMaxima;
use Carbon\Carbon;

class HorizonteCalendario
{
    public static function ate(): Carbon
    {
        $hoje = today();
        $fimDoSemestre = self::fimDoSemestre($hoje);
        $minimo = $hoje->copy()->addWeeks(config('reservas.calendario_minimo_semanas'));

        return $fimDoSemestre->max($minimo);
    }

    public static function fimDoSemestre(Carbon $dia): Carbon
    {
        $fins = collect(config('reservas.fim_dos_semestres'))->sort()->values();

        foreach ([$dia->year, $dia->year + 1] as $ano) {
            foreach ($fins as $fim) {
                $data = Carbon::createFromFormat('Y-m-d', "{$ano}-{$fim}")->startOfDay();

                if ($data->gte($dia->copy()->startOfDay())) {
                    return $data;
                }
            }
        }

        return $dia->copy()->endOfYear()->startOfDay();
    }

    public static function abreEm(Carbon $dia): ?Carbon
    {
        if ($dia->isWeekend() || $dia->lte(AntecedenciaMaxima::dataLimite())) {
            return null;
        }

        return $dia->copy()->subWeekdays(config('reservas.antecedencia_maxima_dias_uteis'));
    }
}
