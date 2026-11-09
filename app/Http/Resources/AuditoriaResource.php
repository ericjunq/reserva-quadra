<?php

namespace App\Http\Resources;

use App\Constants\TipoAuditavelConstant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditoriaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'tipo'             => TipoAuditavelConstant::doApelido($this->auditavel_type)?->value ?? $this->auditavel_type,
            'registro_id'      => $this->auditavel_id,
            'acao'             => $this->acao->value,
            'campos_alterados' => array_keys($this->depois ?? $this->antes ?? []),
            'antes'            => $this->antes,
            'depois'           => $this->depois,
            'feito_por'        => $this->usuario ? ['id' => $this->usuario->id, 'nome' => $this->usuario->name] : null,
            'em'               => $this->created_at,
        ];
    }
}
