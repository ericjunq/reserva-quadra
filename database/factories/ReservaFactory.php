<?php

namespace Database\Factories;

use App\Constants\HorarioReservaConstant;
use App\Constants\StatusConstant;
use App\Constants\TipoReservaConstant;
use App\Models\Aluno;
use App\Models\Espaco;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservaFactory extends Factory
{
    public function definition(): array
    {
        $horario = HorarioReservaConstant::PRIMEIRO_HORARIO;

        return [
            'data_reserva' => today()->nextWeekday()->toDateString(),
            'horario'      => $horario,
            'hora_inicio'  => $horario->horaInicio(),
            'hora_fim'     => $horario->horaFim(),
            'espaco_id'    => Espaco::factory(),
            'aluno_id'     => Aluno::factory(),
            'status'       => StatusConstant::RESERVADO,
            'tipo'         => TipoReservaConstant::ALUNO,
        ];
    }

    public function noHorario(HorarioReservaConstant $horario): static
    {
        return $this->state([
            'horario'     => $horario,
            'hora_inicio' => $horario->horaInicio(),
            'hora_fim'    => $horario->horaFim(),
        ]);
    }

    public function evento(): static
    {
        return $this->state([
            'aluno_id'  => null,
            'criado_por' => User::factory(),
            'tipo'      => TipoReservaConstant::EVENTO,
            'descricao' => 'Evento da faculdade',
        ]);
    }

    public function revogada(): static
    {
        return $this->state([
            'status'                  => StatusConstant::REVOGADO,
            'justificativa_revogacao' => 'Revogada para teste',
            'revogada_em'             => now(),
        ]);
    }
}
