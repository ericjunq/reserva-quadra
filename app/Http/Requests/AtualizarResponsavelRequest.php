<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AtualizarResponsavelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'remover'             => ['sometimes', 'boolean'],
            'responsavel_nome'    => ['exclude_if:remover,true', 'required', 'string', 'min:3', 'max:100'],
            'responsavel_contato' => ['exclude_if:remover,true', 'nullable', 'string', 'max:120'],
        ];
    }
}
