<?php

namespace App\Http\Requests;

use App\Constants\TipoEspacoConstant;
use App\Http\Requests\Concerns\ValidaConfiguracaoDeHorario;
use App\Rules\NomeUnico;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEspacoRequest extends FormRequest
{
    use ValidaConfiguracaoDeHorario;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => [
                'sometimes', 'string', 'max:100',
                new NomeUnico('espacos', $this->route('espaco')->id),
            ],
            'tipo'                     => ['sometimes', Rule::enum(TipoEspacoConstant::class)],
            'ativo'                    => ['sometimes', 'boolean'],
            'limite_semanal_por_curso' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
            ...$this->regrasConfiguracaoDeHorario(edicao: true),
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $espaco = $this->route('espaco');

            $this->validarConfiguracaoDeHorario($validator, [
                'modo_horario'           => $espaco->modo_horario->value,
                'abre_as'                => $espaco->abre_as,
                'fecha_as'               => $espaco->fecha_as,
                'duracao_maxima_minutos' => $espaco->duracao_maxima_minutos,
                ...$this->only(['modo_horario', 'abre_as', 'fecha_as', 'duracao_maxima_minutos']),
            ]);
        }];
    }
}
