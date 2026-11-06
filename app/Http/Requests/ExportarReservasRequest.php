<?php

namespace App\Http\Requests;

use App\Services\ExportacaoReservasService;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ExportarReservasRequest extends ListarReservasRequest
{
    public const MAXIMO_DIAS = 366;

    public function rules(): array
    {
        $regras = parent::rules();
        unset($regras['per_page']);

        return $regras + [
            'colunas'   => ['nullable', 'array'],
            'colunas.*' => ['string', 'distinct', Rule::in([...array_keys(ExportacaoReservasService::COLUNAS_OPCIONAIS), 'todas'])],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty() || ! $this->input('de') || ! $this->input('ate')) {
                    return;
                }

                $dias = Carbon::parse($this->input('de'))->diffInDays(Carbon::parse($this->input('ate'))) + 1;

                if ($dias > self::MAXIMO_DIAS) {
                    $validator->errors()->add('ate', 'O período exportado pode ter no máximo '.self::MAXIMO_DIAS.' dias.');
                }
            },
        ];
    }

    public function colunas(): array
    {
        $colunas = $this->input('colunas', []);

        return in_array('todas', $colunas, true)
            ? array_keys(ExportacaoReservasService::COLUNAS_OPCIONAIS)
            : $colunas;
    }
}
