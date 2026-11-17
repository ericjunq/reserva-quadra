<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipamentoParceiroResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'nome'               => $this->nome,
            'quantidade'         => $this->utilizavel(),
            'aceita_solicitacao' => $this->aceita_solicitacao,
        ];
    }
}
