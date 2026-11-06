<?php

namespace App\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AntecedenciaMaxima implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $dias = config('reservas.antecedencia_maxima_dias');

        if (Carbon::canBeCreatedFromFormat($value, 'Y-m-d')
            && Carbon::createFromFormat('Y-m-d', $value)->startOfDay()->gt(today()->addDays($dias))) {
            $fail("As reservas podem ser feitas com no máximo {$dias} dias de antecedência.");
        }
    }
}
