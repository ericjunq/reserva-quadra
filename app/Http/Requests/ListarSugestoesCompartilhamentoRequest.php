<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListarSugestoesCompartilhamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'equipamento_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
