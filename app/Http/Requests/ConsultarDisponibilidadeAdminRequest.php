<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ConsultarDisponibilidadeAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'de'  => ['required', 'date_format:Y-m-d'],
            'ate' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:de'],
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

                if ($dias > ConsultarDisponibilidadeRequest::MAXIMO_DIAS) {
                    $validator->errors()->add('ate', 'O período consultado pode ter no máximo '.ConsultarDisponibilidadeRequest::MAXIMO_DIAS.' dias.');
                }
            },
        ];
    }

    public function ate(): string
    {
        return $this->input('ate') ?: $this->input('de');
    }
}
