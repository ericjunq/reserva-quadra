<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipamentoDaReservaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => (int) $this->id,
            'nome'               => (string) $this->nome,
            'quantidade'         => (int) $this->pivot->quantidade,
            'situacao_do_pedido' => $this->pivot->situacao_solicitacao ? (string) $this->pivot->situacao_solicitacao->value : null,
        ];
    }
}
