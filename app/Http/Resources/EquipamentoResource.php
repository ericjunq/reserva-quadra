<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ExibeAuditoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipamentoResource extends JsonResource
{
    use ExibeAuditoria;

    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'espaco_id'        => $this->espaco_id,
            'nome'             => $this->nome,
            'quantidade_total' => $this->quantidade_total,
            'ativo'            => $this->ativo,
            'compartilhavel'   => $this->compartilhavel,
            $this->auditoria(),
        ];
    }
}
