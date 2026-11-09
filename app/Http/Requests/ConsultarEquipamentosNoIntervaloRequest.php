<?php

namespace App\Http\Requests;

use App\Support\IntervaloReserva;
use Illuminate\Foundation\Http\FormRequest;

class ConsultarEquipamentosNoIntervaloRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data'        => ['required', 'date_format:Y-m-d'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fim'    => ['required', 'date_format:H:i', 'after:hora_inicio'],
        ];
    }

    public function intervalo(): array
    {
        return [
            IntervaloReserva::normalizar($this->input('hora_inicio')),
            IntervaloReserva::normalizar($this->input('hora_fim')),
        ];
    }
}
