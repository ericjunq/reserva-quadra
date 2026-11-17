<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarRetiradaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'itens'                       => ['required', 'array', 'min:1'],
            'itens.*.equipamento_id'      => ['required', 'integer', 'distinct'],
            'itens.*.quantidade_retirada' => ['required', 'integer', 'min:0'],
        ];
    }
}
