<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidaEquipamentosDaReserva;
use App\Http\Requests\Concerns\ValidaHorarioDaReserva;
use App\Rules\AntecedenciaMaxima;
use App\Rules\DataNaoBloqueada;
use App\Rules\DiaUtil;
use App\Support\IntervaloReserva;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReservaRequest extends FormRequest
{
    use ValidaEquipamentosDaReserva, ValidaHorarioDaReserva;

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
            ...$this->regrasSolicitacoes(),
            ...$this->regrasHorario(),
            'data_reserva'       => ['required', 'date_format:Y-m-d', 'after_or_equal:today', new DiaUtil, new AntecedenciaMaxima, new DataNaoBloqueada],
            'registro_academico' => ['required', 'string', 'size:10', Rule::exists('alunos', 'registro_academico')->where('ativo', true)],
            'espaco_id'          => ['required', 'integer', Rule::exists('espacos', 'id')->where('ativo', true)],
        ];
    }

    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validarHorarioDoEspaco($validator, respeitarJanela: true),
            fn (Validator $validator) => $this->validarEstoqueDosEquipamentos($validator),
            fn (Validator $validator) => $this->validarAntecedenciaDasSolicitacoes($validator),
        ];
    }

    private function validarAntecedenciaDasSolicitacoes(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty() || ! $this->input('solicitacoes')) {
            return;
        }

        $intervalo = IntervaloReserva::doPedido($this->all());
        $horas = (int) config('reservas.solicitacao_vence_horas_antes');

        if ($intervalo && Carbon::parse($this->input('data_reserva').' '.$intervalo[0])->lt(now()->addHours($horas))) {
            $validator->errors()->add('solicitacoes', "Pedidos de equipamento de outro espaço precisam de pelo menos {$horas} horas de antecedência.");
        }
    }
}
