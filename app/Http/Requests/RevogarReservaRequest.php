<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RevogarReservaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'justificativa' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }
}
