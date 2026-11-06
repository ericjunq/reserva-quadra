<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDataBloqueadaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data_inicio' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'data_fim'    => ['required', 'date_format:Y-m-d', 'after_or_equal:data_inicio'],
            'hora_inicio' => ['nullable', 'date_format:H:i', 'required_with:hora_fim'],
            'hora_fim'    => ['nullable', 'date_format:H:i', 'required_with:hora_inicio', 'after:hora_inicio'],
            'espaco_id'   => ['nullable', 'integer', 'exists:espacos,id'],
            'motivo'      => ['required', 'string', 'max:255'],
        ];
    }
}
