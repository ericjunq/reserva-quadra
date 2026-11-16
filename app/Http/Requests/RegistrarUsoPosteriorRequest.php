<?php

namespace App\Http\Requests;

use App\Constants\HorarioReservaConstant;
use App\Http\Requests\Concerns\ValidaEquipamentosDaReserva;
use App\Http\Requests\Concerns\ValidaHorarioDaReserva;
use App\Rules\DataNaoBloqueada;
use App\Support\IntervaloReserva;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RegistrarUsoPosteriorRequest extends FormRequest
{
    use ValidaEquipamentosDaReserva, ValidaHorarioDaReserva;

    public const DIAS_PARA_TRAS = 30;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->arredondarIntervalo();
    }

    public function rules(): array
    {
        return [
            ...$this->regrasEquipamentos(),
            'espaco_id'    => ['required', 'integer', Rule::exists('espacos', 'id')->where('ativo', true)],
            'data_reserva' => [
                'required', 'date_format:Y-m-d',
                'after_or_equal:'.today()->subDays(self::DIAS_PARA_TRAS)->toDateString(),
                'before_or_equal:today',
                new DataNaoBloqueada,
            ],
            'horario'            => ['nullable', Rule::enum(HorarioReservaConstant::class)],
            'hora_inicio'        => ['nullable', 'date_format:H:i'],
            'hora_fim'           => ['nullable', 'date_format:H:i'],
            'registro_academico' => [
                'nullable', 'required_without:descricao', 'prohibits:descricao', 'string', 'size:10',
                Rule::exists('alunos', 'registro_academico')->where('ativo', true),
            ],
            'descricao' => ['nullable', 'required_without:registro_academico', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'data_reserva.after_or_equal'         => 'Só dá para registrar usos dos últimos '.self::DIAS_PARA_TRAS.' dias.',
            'data_reserva.before_or_equal'        => 'Para datas futuras, use a reserva ou o evento.',
            'registro_academico.required_without' => 'Informe o RA do aluno ou a descrição do grupo.',
            'descricao.required_without'          => 'Informe o RA do aluno ou a descrição do grupo.',
            'registro_academico.prohibits'        => 'Informe o RA do aluno ou a descrição do grupo, não os dois.',
            'registro_academico.exists'           => 'Não há aluno ativo com esse RA.',
        ];
    }

    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validarHorarioDoEspaco($validator, respeitarJanela: false),
            fn (Validator $validator) => $this->validarInicioJaPassou($validator),
            fn (Validator $validator) => $this->validarEstoqueDosEquipamentos($validator),
        ];
    }

    private function validarInicioJaPassou(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty() || $this->input('data_reserva') !== today()->toDateString()) {
            return;
        }

        [$inicio] = IntervaloReserva::doPedido($this->all()) ?? [null];

        if ($inicio && $inicio > now()->format('H:i:s')) {
            $validator->errors()->add('hora_inicio', 'Para hoje, só dá para registrar um uso que já começou.');
        }
    }
}
