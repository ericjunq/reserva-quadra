<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use App\Http\Resources\Concerns\ExibeAuditoria;
use Illuminate\Http\Resources\Json\JsonResource;

class EspacoResource extends JsonResource
{
    use ExibeAuditoria;

    public function toArray(Request $request): array
    {
        return [
            'id'    => $this->id,
            'nome'  => $this->nome,
            'tipo'  => $this->tipo->value,
            'ativo' => $this->ativo,
            'limite_semanal_por_curso' => $this->limite_semanal_por_curso,
            $this->auditoria(),
        ];
    }
}
