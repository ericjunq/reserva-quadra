<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AceitarTermosApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'aceite_termos' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'aceite_termos.accepted' => 'Para liberar o acesso, leia e aceite os termos de uso da API.',
        ];
    }
}
