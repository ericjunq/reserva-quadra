<?php

namespace App\Http\Requests;

use App\Constants\PermissaoApiConstant;
use App\Models\User;
use App\Rules\NomeUnico;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClienteApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('responsavel_email'))) {
            $this->merge(['responsavel_email' => User::normalizarEmail($this->input('responsavel_email'))]);
        }
    }

    public function rules(): array
    {
        $obrigatorio = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'nome'              => [$obrigatorio, 'string', 'max:100', new NomeUnico('clientes_api', $this->route('clienteApi')?->id)],
            'organizacao'       => [$obrigatorio, 'string', 'max:150'],
            'responsavel_nome'  => [$obrigatorio, 'string', 'max:100'],
            'responsavel_email' => [$obrigatorio, 'string', 'email', 'max:150'],
            'finalidade'        => [$obrigatorio, 'string', 'min:10', 'max:1000'],
            'permissoes'        => [$obrigatorio, 'array', 'min:1'],
            'permissoes.*'      => ['distinct', Rule::enum(PermissaoApiConstant::class)],
            'limite_por_minuto' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:600'],
        ];
    }

    public function validated($key = null, $default = null)
    {
        $dados = parent::validated($key, $default);

        if ($key === null && isset($dados['permissoes'])) {
            $dados['permissoes'] = array_values(array_intersect(PermissaoApiConstant::valores(), $dados['permissoes']));
        }

        return $dados;
    }
}
