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
            'id'                       => $this->id,
            'espaco_id'                => $this->espaco_id,
            'nome'                     => $this->nome,
            'quantidade_total'         => $this->quantidade_total,
            'quantidade_em_manutencao' => $this->when($request->is('api/admin/*'), $this->quantidade_em_manutencao),
            'ativo'                    => $this->ativo,
            'aceita_solicitacao'       => $this->aceita_solicitacao,
            'espacos_compartilhados'   => $this->whenLoaded(
                'espacosCompartilhados',
                fn () => $this->espacosCompartilhados->map(fn ($espaco) => ['id' => $espaco->id, 'nome' => $espaco->nome])->values(),
            ),
            $this->auditoria(),
        ];
    }
}
