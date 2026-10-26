<?php

namespace App\Services;
use App\Models\Aluno;
use App\Models\Reserva;
use DateTime;

class ReservaService
{
    public function create($data)
    {
        if (
            Reserva::where('horaro_inicio', $data->horario_inicio)
                ->where('horario_fim')
                ->where('status', 'reservado')
                ->exists()
        ) {
            throw new \Exception("Já existe uma reserva ativa para esse horário");
        }

        $aluno = Aluno::whereRegistro_academico($data['registro_academico'])->first();

        if (!$aluno) {
            throw new \Exception("Aluno não encontrado");
        }

        $this->validarData($data);

        $dados_com_id = $data->safe()
            ->merge(['aluno_id' => $aluno->id])
            ->except(['registro_academico']);

        $reserva = Reserva::create($dados_com_id);

        return $reserva;

    }


    private function validarData($data)
    {
        $inicio = new DateTime($data['horario_inicio']->getTimestamp());
        $fim = new DateTime($data['horario_fim']->getTimestamp());

        $diferenca = $fim - $inicio;

        if ($diferenca != 5400) {
            throw new \Exception("Intervalo de tempo inserido é inválido");
        }
    }
}
