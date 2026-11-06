<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EspacoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'    => $this->id,
            'nome'  => $this->nome,
            'tipo'  => $this->tipo->value,
            'ativo' => $this->ativo,
            'limite_semanal_por_curso' => $this->limite_semanal_por_curso,
        ];
    }
}
