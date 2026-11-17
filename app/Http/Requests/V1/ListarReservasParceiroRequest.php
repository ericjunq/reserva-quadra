<?php

namespace App\Http\Requests\V1;

use App\Constants\StatusConstant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarReservasParceiroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'de'     => ['nullable', 'date_format:Y-m-d'],
            'ate'    => ['nullable', 'date_format:Y-m-d', 'after_or_equal:de'],
            'status' => ['nullable', Rule::enum(StatusConstant::class)],
        ];
    }

    public function filtros(): array
    {
        return $this->only(['de', 'ate', 'status']);
    }
}
