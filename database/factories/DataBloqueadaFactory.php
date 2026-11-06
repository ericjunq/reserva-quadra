<?php

namespace Database\Factories;

use App\Constants\OrigemBloqueioConstant;
use Illuminate\Database\Eloquent\Factories\Factory;

class DataBloqueadaFactory extends Factory
{
    public function definition(): array
    {
        $data = today()->nextWeekday()->toDateString();

        return [
            'data_inicio' => $data,
            'data_fim'    => $data,
            'hora_inicio' => null,
            'hora_fim'    => null,
            'espaco_id'   => null,
            'motivo'      => 'Recesso acadêmico',
            'origem'      => OrigemBloqueioConstant::MANUAL,
        ];
    }

    public function entre(string $inicio, string $fim): static
    {
        return $this->state(['data_inicio' => $inicio, 'data_fim' => $fim]);
    }

    public function dasHoras(string $inicio, string $fim): static
    {
        return $this->state(['hora_inicio' => $inicio, 'hora_fim' => $fim]);
    }
}
