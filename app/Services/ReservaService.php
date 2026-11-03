<?php

namespace App\Services;

use App\Constants\StatusConstant;
use App\Models\Aluno;
use App\Models\Reserva;
use Exception;
use Illuminate\Support\Facades\DB;

class ReservaService
{
    public function create(array $data): Reserva
    {
        $aluno = Aluno::where('registro_academico', $data['registro_academico'])->firstOrFail();

        return DB::transaction(function () use ($aluno, $data) {
            $this->verificarDisponibilidade($data);

            return $aluno->reservas()->create([
                'data'    => $data['data'],
                'horario' => $data['horario'],
            ]);
        });
    }

    private function verificarDisponibilidade(array $data): void
    {
        if (Reserva::where('data', $data['data'])
            ->where('horario', $data['horario'])
            ->where('status', StatusConstant::RESERVADO)
            ->lockForUpdate()
            ->exists()
        ) {
            throw new Exception('Já existe uma reserva para esse horário');
        }
    }
}
