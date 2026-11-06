<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ExibeAuditoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CursoResource extends JsonResource
{
    use ExibeAuditoria;

    public function toArray(Request $request): array
    {
        return [
            'id'    => $this->id,
            'nome'  => $this->nome,
            'ativo' => $this->ativo,
            $this->auditoria(),
        ];
    }
}
