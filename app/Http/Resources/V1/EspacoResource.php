<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EspacoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => (int) $this->id,
            'nome'                     => (string) $this->nome,
            'tipo'                     => (string) $this->tipo->value,
            'modo_horario'             => (string) $this->modo_horario->value,
            'abre_as'                  => $this->abre_as ? substr($this->abre_as, 0, 5) : null,
            'fecha_as'                 => $this->fecha_as ? substr($this->fecha_as, 0, 5) : null,
            'duracao_maxima_minutos'   => $this->duracao_maxima_minutos === null ? null : (int) $this->duracao_maxima_minutos,
            'limite_semanal_por_curso' => $this->limite_semanal_por_curso === null ? null : (int) $this->limite_semanal_por_curso,
        ];
    }
}
