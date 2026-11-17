<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

class StoreReservaAdminRequest extends StoreReservaRequest
{
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validarHorarioDoEspaco($validator, respeitarJanela: true),
            fn (Validator $validator) => $this->validarEstoqueDosEquipamentos($validator),
        ];
    }
}
