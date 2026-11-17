<?php

namespace App\Http\Requests;

class UpdateClienteApiRequest extends StoreClienteApiRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'limite_por_minuto' => ['sometimes', 'integer', 'min:1', 'max:600'],
            'ativo'             => ['sometimes', 'boolean'],
        ];
    }
}
