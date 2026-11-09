<?php

namespace App\Http\Requests;

use App\Constants\TipoEspacoConstant;
use App\Http\Requests\Concerns\ValidaConfiguracaoDeHorario;
use App\Rules\NomeUnico;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEspacoRequest extends FormRequest
{
    use ValidaConfiguracaoDeHorario;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome'                     => ['required', 'string', 'max:100', new NomeUnico('espacos')],
            'tipo'                     => ['required', Rule::enum(TipoEspacoConstant::class)],
            'limite_semanal_por_curso' => ['nullable', 'integer', 'min:1', 'max:50'],
            ...$this->regrasConfiguracaoDeHorario(edicao: false),
        ];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->validarConfiguracaoDeHorario($validator, $this->all())];
    }
}
