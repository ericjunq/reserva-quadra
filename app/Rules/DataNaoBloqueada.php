<?php

namespace App\Rules;

use App\Models\DataBloqueada;
use App\Support\IntervaloReserva;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class DataNaoBloqueada implements DataAwareRule, ValidationRule
{
    private array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $intervalo = IntervaloReserva::doPedido($this->data);
        $espacoId = $this->data['espaco_id'] ?? null;

        if (! $intervalo || ! is_numeric($espacoId) || ! Carbon::canBeCreatedFromFormat($value, 'Y-m-d')) {
            return;
        }

        $bloqueio = DataBloqueada::afetando($value, (int) $espacoId, $intervalo[0], $intervalo[1])->first();

        if (! $bloqueio) {
            return;
        }

        $dia = Carbon::parse($value)->format('d/m/Y');

        $fail($bloqueio->diaInteiro()
            ? "Data indisponível em {$dia}: {$bloqueio->motivo}."
            : 'Horário indisponível em '.$dia.' ('.substr($bloqueio->hora_inicio, 0, 5).'–'.substr($bloqueio->hora_fim, 0, 5)."): {$bloqueio->motivo}."
        );
    }
}
