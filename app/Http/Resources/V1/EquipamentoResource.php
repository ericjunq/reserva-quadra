<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipamentoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => (int) $this->id,
            'nome'               => (string) $this->nome,
            'quantidade'         => (int) $this->utilizavel(),
            'aceita_solicitacao' => (bool) $this->aceita_solicitacao,
        ];
    }
}
