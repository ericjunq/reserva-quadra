<?php

namespace App\Http\Requests\Concerns;

use App\Constants\HorarioReservaConstant;
use App\Models\Espaco;
use App\Rules\HorarioAindaReservavel;
use App\Support\IntervaloReserva;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidaHorarioDaReserva
{
    protected function regrasHorario(): array
    {
        return [
            'horario'     => ['nullable', Rule::enum(HorarioReservaConstant::class), new HorarioAindaReservavel],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'hora_fim'    => ['nullable', 'date_format:H:i', new HorarioAindaReservavel],
        ];
    }

    protected function validarHorarioDoEspaco(Validator $validator, bool $respeitarJanela): void
    {
        if ($validator->errors()->hasAny(['espaco_id', 'horario', 'hora_inicio', 'hora_fim'])) {
            return;
        }

        $espaco = Espaco::find($this->input('espaco_id'));

        if (! $espaco) {
            return;
        }

        $livre = $this->filled('hora_inicio') || $this->filled('hora_fim');

        if (! $espaco->livre()) {
            if ($livre) {
                $validator->errors()->add('hora_inicio', 'Este espaço usa horários fixos: envie apenas "horario".');
            } elseif (! $this->filled('horario')) {
                $validator->errors()->add('horario', 'Informe o horário.');
            }

            return;
        }

        if ($this->filled('horario')) {
            $validator->errors()->add('horario', 'Este espaço tem horário livre: envie "hora_inicio" e "hora_fim".');

            return;
        }

        if (! $this->filled('hora_inicio') || ! $this->filled('hora_fim')) {
            $validator->errors()->add('hora_inicio', 'Informe "hora_inicio" e "hora_fim".');

            return;
        }

        $inicio = IntervaloReserva::normalizar($this->input('hora_inicio'));
        $fim = IntervaloReserva::normalizar($this->input('hora_fim'));
        $bloco = IntervaloReserva::bloco();

        if (! IntervaloReserva::alinhada($inicio) || ! IntervaloReserva::alinhada($fim)) {
            $validator->errors()->add('hora_inicio', "Os horários precisam ser múltiplos de {$bloco} minutos (ex.: 14:00, 14:30).");

            return;
        }

        if ($fim <= $inicio) {
            $validator->errors()->add('hora_fim', 'O fim precisa ser depois do início.');

            return;
        }

        if (! $respeitarJanela) {
            return;
        }

        if ($inicio < $espaco->abre_as || $fim > $espaco->fecha_as) {
            $janela = IntervaloReserva::curta($espaco->abre_as).'–'.IntervaloReserva::curta($espaco->fecha_as);
            $validator->errors()->add('hora_inicio', "Fora do horário de funcionamento do espaço ({$janela}).");
        }

        $duracao = IntervaloReserva::minutos($fim) - IntervaloReserva::minutos($inicio);

        if ($duracao > $espaco->duracao_maxima_minutos) {
            $validator->errors()->add('hora_fim', "A duração máxima neste espaço é de {$espaco->duracao_maxima_minutos} minutos.");
        }
    }
}
