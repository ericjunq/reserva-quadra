<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SugestaoCompartilhamentoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $equipamento = $this->resource['equipamento'];

        return [
            'equipamento'      => ['id' => $equipamento->id, 'nome' => $equipamento->nome],
            'espaco_de_origem' => ['id' => $equipamento->espaco->id, 'nome' => $equipamento->espaco->nome],
            'espaco_que_pede'  => ['id' => $this->resource['espaco']->id, 'nome' => $this->resource['espaco']->nome],
            'pedidos'          => $this->resource['pedidos'],
            'ultimo_pedido_em' => Carbon::parse($this->resource['ultimo_pedido_em'])->toIso8601String(),
        ];
    }
}
