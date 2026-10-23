<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AlunoRequest extends FormRequest
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
            "nome_completo" => ["required", 'string', 'max:150'],
            "registro_academico" => ['required', 'string', 'size:10'],
            "email" => ['required', 'string', 'max:100'],
            "curso" => ['required', 'string', 'max:30'],
        ];
    }
}
