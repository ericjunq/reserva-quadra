<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConsultarMinhasReservasRequest extends FormRequest
{
    public const FUTURAS = 'FUTURAS';

    public const TODAS = 'TODAS';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'registro_academico' => ['required', 'string', 'size:10'],
            'email'              => ['required', 'string', 'email', 'max:100'],
            'periodo'            => ['nullable', 'in:'.self::FUTURAS.','.self::TODAS],
        ];
    }

    public function somenteFuturas(): bool
    {
        return $this->input('periodo', self::FUTURAS) === self::FUTURAS;
    }
}
