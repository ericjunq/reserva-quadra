<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReenviarConfirmacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'registro_academico' => ['required', 'string', 'size:10'],
            'email'              => ['required', 'string', 'email', 'max:100'],
            'reserva_id'         => ['required', 'integer'],
        ];
    }
}
