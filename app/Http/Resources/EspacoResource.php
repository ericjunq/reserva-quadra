<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ExibeAuditoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EspacoResource extends JsonResource
{
    use ExibeAuditoria;

    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'nome'                     => $this->nome,
            'tipo'                     => $this->tipo->value,
            'ativo'                    => $this->ativo,
            'limite_semanal_por_curso' => $this->limite_semanal_por_curso,
            'modo_horario'             => $this->modo_horario->value,
            'abre_as'                  => $this->abre_as ? substr($this->abre_as, 0, 5) : null,
            'fecha_as'                 => $this->fecha_as ? substr($this->fecha_as, 0, 5) : null,
            'duracao_maxima_minutos'   => $this->duracao_maxima_minutos,
            $this->auditoria(),
        ];
    }
}
