<?php

namespace App\Http\Requests\Concerns;

use App\Models\Equipamento;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidaEquipamentosDaReserva
{
    protected function regrasEquipamentos(): array
    {
        return [
            'equipamentos'      => ['sometimes', 'array'],
            'equipamentos.*.id' => [
                'required', 'integer', 'distinct',
                Rule::exists('equipamentos', 'id')
                    ->where('ativo', true)
                    ->where(fn ($q) => $q->where(fn ($q) => $q
                        ->where('espaco_id', (int) $this->input('espaco_id'))
                        ->orWhereIn('id', fn ($sub) => $sub
                            ->select('equipamento_id')
                            ->from('equipamento_compartilhamentos')
                            ->where('espaco_id', (int) $this->input('espaco_id'))))),
            ],
            'equipamentos.*.quantidade' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function regrasSolicitacoes(): array
    {
        $espacoId = (int) $this->input('espaco_id');

        return [
            'solicitacoes'      => ['sometimes', 'array'],
            'solicitacoes.*.id' => [
                'required', 'integer', 'distinct',
                Rule::exists('equipamentos', 'id')
                    ->where('ativo', true)
                    ->where('aceita_solicitacao', true)
                    ->where(fn ($q) => $q
                        ->where('espaco_id', '!=', $espacoId)
                        ->whereIn('espaco_id', fn ($sub) => $sub->select('id')->from('espacos')->where('ativo', true))
                        ->whereNotIn('id', fn ($sub) => $sub
                            ->select('equipamento_id')
                            ->from('equipamento_compartilhamentos')
                            ->where('espaco_id', $espacoId))),
            ],
            'solicitacoes.*.quantidade' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function validarEstoqueDosEquipamentos(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        foreach (['equipamentos', 'solicitacoes'] as $campo) {
            $itens = collect($this->input($campo, []));
            $estoques = Equipamento::whereIn('id', $itens->pluck('id'))->pluck('quantidade_total', 'id');

            foreach ($itens as $i => $item) {
                $total = $estoques[$item['id']];

                if ($item['quantidade'] > $total) {
                    $validator->errors()->add(
                        "{$campo}.{$i}.quantidade",
                        "A quantidade não pode ser maior que o estoque total ({$total}).",
                    );
                }
            }
        }
    }
}
