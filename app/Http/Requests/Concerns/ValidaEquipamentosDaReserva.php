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

    protected function validarEstoqueDosEquipamentos(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $itens = collect($this->input('equipamentos', []));
        $estoques = Equipamento::whereIn('id', $itens->pluck('id'))->pluck('quantidade_total', 'id');

        foreach ($itens as $i => $item) {
            $total = $estoques[$item['id']];

            if ($item['quantidade'] > $total) {
                $validator->errors()->add(
                    "equipamentos.{$i}.quantidade",
                    "A quantidade não pode ser maior que o estoque total ({$total}).",
                );
            }
        }
    }
}
