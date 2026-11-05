<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlunoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'nome_completo'      => $this->nome_completo,
            'registro_academico' => $this->registro_academico,
            'email'              => $this->email,
            'curso'              => new CursoResource($this->whenLoaded('curso')),
            'created_at'         => $this->created_at,
        ];
    }
}
