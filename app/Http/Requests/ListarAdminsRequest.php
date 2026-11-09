<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListarAdminsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'busca' => ['nullable', 'string', 'max:100'],
            'ativo' => ['nullable', 'in:0,1,true,false'],
        ];
    }

    public function filtros(): array
    {
        return [
            'busca' => $this->input('busca'),
            'ativo' => $this->filled('ativo') ? $this->boolean('ativo') : null,
        ];
    }
}
