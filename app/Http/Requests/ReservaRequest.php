<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use App\Constants\HorarioReservaConstant;
use Illuminate\Validation\Rule;

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
            "horario" => ['required', Rule::enum(HorarioReservaConstant::class)],
            "data" => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            "registro_academico" => ['required', 'string', 'size:10', 'exists:alunos,registro_academico'],

        ];
    }
}
