<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmarCodigoLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'desafio' => ['required', 'string', 'size:64'],
            'codigo'  => ['required', 'string', 'regex:/^\d{6}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.regex' => 'O código tem 6 números.',
        ];
    }
}
