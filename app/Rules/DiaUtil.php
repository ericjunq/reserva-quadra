<?php

namespace App\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DiaUtil implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (Carbon::canBeCreatedFromFormat($value, 'Y-m-d') && Carbon::createFromFormat('Y-m-d', $value)->isWeekend()) {
            $fail('Não é possível reservar em fins de semana.');
        }
    }
}
