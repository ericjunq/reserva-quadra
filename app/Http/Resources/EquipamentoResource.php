<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipamentoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'espaco_id' => $this->espaco_id,
            'nome' => $this->nome,
            'quantidade_total' => $this->quantidade_total,
            'ativo' => $this->ativo,
            'compartilhavel' => $this->compartilhavel,
        ];
    }
}
