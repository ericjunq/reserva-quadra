<?php

namespace App\Http\Requests\Concerns;

use App\Constants\ModoHorarioConstant;
use App\Support\IntervaloReserva;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidaConfiguracaoDeHorario
{
    protected function regrasConfiguracaoDeHorario(bool $edicao): array
    {
        $prefixo = $edicao ? ['sometimes'] : [];

        return [
            'modo_horario'           => [...$prefixo, 'nullable', Rule::enum(ModoHorarioConstant::class)],
            'abre_as'                => [...$prefixo, 'nullable', 'date_format:H:i'],
            'fecha_as'               => [...$prefixo, 'nullable', 'date_format:H:i'],
            'duracao_maxima_minutos' => [...$prefixo, 'nullable', 'integer', 'min:1', 'max:1440'],
        ];
    }

    protected function validarConfiguracaoDeHorario(Validator $validator, array $efetivo): void
    {
        if ($validator->errors()->hasAny(['modo_horario', 'abre_as', 'fecha_as', 'duracao_maxima_minutos'])) {
            return;
        }

        if (($efetivo['modo_horario'] ?? 'FIXO') !== ModoHorarioConstant::LIVRE->value) {
            return;
        }

        $abre = IntervaloReserva::normalizar($efetivo['abre_as'] ?? null);
        $fecha = IntervaloReserva::normalizar($efetivo['fecha_as'] ?? null);
        $duracao = $efetivo['duracao_maxima_minutos'] ?? null;
        $bloco = IntervaloReserva::bloco();

        foreach (['abre_as' => $abre, 'fecha_as' => $fecha, 'duracao_maxima_minutos' => $duracao] as $campo => $valor) {
            if ($valor === null) {
                $validator->errors()->add($campo, 'Obrigatório quando o espaço tem horário livre.');
            }
        }

        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        if (! IntervaloReserva::alinhada($abre) || ! IntervaloReserva::alinhada($fecha)) {
            $validator->errors()->add('abre_as', "Os horários precisam ser múltiplos de {$bloco} minutos.");
        }

        if ($fecha <= $abre) {
            $validator->errors()->add('fecha_as', 'O fechamento precisa ser depois da abertura.');
        }

        if ((int) $duracao % $bloco !== 0 || (int) $duracao < $bloco) {
            $validator->errors()->add('duracao_maxima_minutos', "A duração máxima precisa ser múltipla de {$bloco} minutos.");
        } elseif ($fecha > $abre && (int) $duracao > IntervaloReserva::minutos($fecha) - IntervaloReserva::minutos($abre)) {
            $validator->errors()->add('duracao_maxima_minutos', 'A duração máxima não pode passar da janela de funcionamento.');
        }
    }
}
