<?php

namespace App\Rules;

use App\Constants\HorarioReservaConstant;
use App\Models\DataBloqueada;
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
        $horario = HorarioReservaConstant::tryFrom((string) ($this->data['horario'] ?? ''));
        $espacoId = $this->data['espaco_id'] ?? null;

        if (! $horario || ! is_numeric($espacoId) || ! Carbon::canBeCreatedFromFormat($value, 'Y-m-d')) {
            return;
        }

        $bloqueio = DataBloqueada::afetando($value, (int) $espacoId, $horario->horaInicio(), $horario->horaFim())->first();

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
