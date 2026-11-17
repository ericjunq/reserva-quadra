<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ExibeAuditoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClienteApiResource extends JsonResource
{
    use ExibeAuditoria;

    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'nome'              => $this->nome,
            'organizacao'       => $this->organizacao,
            'responsavel'       => ['nome' => $this->responsavel_nome, 'email' => $this->responsavel_email],
            'finalidade'        => $this->finalidade,
            'permissoes'        => $this->permissoes,
            'limite_por_minuto' => $this->limite_por_minuto,
            'ativo'             => $this->ativo,
            'situacao'          => $this->situacao()->value,
            'termos_versao'     => $this->termos_versao,
            'termos_aceitos_em' => $this->termos_aceitos_em,
            'acesso_expira_em'  => $this->acesso_expira_em,
            'link_aceite'       => $this->linkDeAceitePendente() ? ['expira_em' => $this->aceite_expira_em] : null,
            $this->auditoria(),
        ];
    }
}
