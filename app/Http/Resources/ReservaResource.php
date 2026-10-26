<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservaResource extends JsonResource
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
            'horario_inicio' => $this->horario_inicio->format('Y-m-d H:i'),
            'horario_fim' => $this->horario_fim->format('Y-m-d H:i'),
            'aluno_id' => $this->aluno_id,
        ];
    }
}
