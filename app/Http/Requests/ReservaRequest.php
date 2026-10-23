<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReservaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            "horario_inicio" => ['required', 'date_format:Y-m-d H:i:s'],
            "horario_fim" => ['required', 'date_format:Y-m-d H:i:s'],
            "registro_academico" => ['required', 'string', 'size:10'],
            "status" => ['required', 'string', 'max:20'],
        ];
    }
}
