<?php

namespace App\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AntecedenciaMaxima implements ValidationRule
{
    public static function dataLimite(): Carbon
    {
        return today()->addWeekdays(config('reservas.antecedencia_maxima_dias_uteis'));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (Carbon::canBeCreatedFromFormat($value, 'Y-m-d')
            && Carbon::createFromFormat('Y-m-d', $value)->startOfDay()->gt(self::dataLimite())) {
            $dias = config('reservas.antecedencia_maxima_dias_uteis');
            $fail("As reservas podem ser feitas com no máximo {$dias} dias úteis de antecedência (até " . self::dataLimite()->format('d/m/Y') . ').');
        }
    }
}
