<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnonimizarAlunoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirmar' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmar.accepted' => 'A anonimização é irreversível. Envie "confirmar": true para prosseguir.',
        ];
    }
}
