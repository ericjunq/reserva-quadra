<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ExibeAuditoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DataBloqueadaResource extends JsonResource
{
    use ExibeAuditoria;

    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'data_inicio' => $this->data_inicio->toDateString(),
            'data_fim'    => $this->data_fim->toDateString(),
            'dia_inteiro' => $this->diaInteiro(),
            'hora_inicio' => $this->hora_inicio ? substr($this->hora_inicio, 0, 5) : null,
            'hora_fim'    => $this->hora_fim ? substr($this->hora_fim, 0, 5) : null,
            'espaco'      => $this->espaco ? ['id' => $this->espaco->id, 'nome' => $this->espaco->nome] : null,
            'motivo'      => $this->motivo,
            'origem'      => $this->origem->value,
            $this->auditoria(),
        ];
    }
}
