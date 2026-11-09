<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class TrocarSenhaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'senha_atual' => ['required', 'string', 'current_password:sanctum'],
            'password'    => ['required', 'confirmed', 'different:senha_atual', Password::min(8)->letters()->numbers()],
        ];
    }

    public function messages(): array
    {
        return [
            'senha_atual.current_password' => 'A senha atual está incorreta.',
            'password.different'           => 'A senha nova precisa ser diferente da atual.',
        ];
    }
}
