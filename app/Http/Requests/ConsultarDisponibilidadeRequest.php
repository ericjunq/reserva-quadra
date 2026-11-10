<?php

namespace App\Http\Requests;

use App\Models\Curso;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ConsultarDisponibilidadeRequest extends FormRequest
{
    public const MAXIMO_DIAS = 31;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'de'       => ['required', 'date_format:Y-m-d'],
            'ate'      => ['nullable', 'date_format:Y-m-d', 'after_or_equal:de'],
            'curso_id' => ['nullable', 'integer', 'exists:cursos,id'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $dias = Carbon::parse($this->input('de'))->diffInDays(Carbon::parse($this->ate())) + 1;

                if ($dias > self::MAXIMO_DIAS) {
                    $validator->errors()->add('ate', 'O período consultado pode ter no máximo '.self::MAXIMO_DIAS.' dias.');
                }
            },
        ];
    }

    public function ate(): string
    {
        return $this->input('ate') ?: $this->input('de');
    }

    public function curso(): ?Curso
    {
        return $this->input('curso_id') ? Curso::find($this->input('curso_id')) : null;
    }
}
