<?php

namespace App\Services;
use App\Models\Aluno;
use App\Models\Reserva;

class ReservaService
{
    public function create($data)
    {
        if(Reserva::where('horaro_inicio', $data->horario_inicio).where('horario_fim').where('status', 'reservado')->exists())
            {
                throw new \Exception("Já existe uma reserva ativa para esse horário");
            }

        if (Aluno::whereRegistro_academico())

        $reserva = Reserva::create($data);

        return $reserva;

    }
}
