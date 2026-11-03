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
        $data = $this->data->format('Y-m-d');

        return [
            'id'             => $this->id,
            'data'           => $data,
            'horario'        => $this->horario->value,
            'horario_inicio' => "{$data} {$this->horario->inicio()}",
            'horario_fim'    => "{$data} {$this->horario->fim()}",
            'status'         => $this->status->value,
            'aluno_id'       => $this->aluno_id,
        ];
    }
}
