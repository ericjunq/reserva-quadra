<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ExibeAuditoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    use ExibeAuditoria;

    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'nome'              => $this->name,
            'email'             => $this->email,
            'ativo'             => $this->ativo,
            'convite_aceito_em' => $this->convite_aceito_em,
            $this->auditoria(),
        ];
    }
}
