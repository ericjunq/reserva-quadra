<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReenviarCodigoLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'desafio' => ['required', 'string', 'size:64'],
        ];
    }
}
