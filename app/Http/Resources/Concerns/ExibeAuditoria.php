<?php

namespace App\Http\Resources\Concerns;

trait ExibeAuditoria
{
    protected function auditoria(): mixed
    {
        $carregada = $this->relationLoaded('criador') && $this->relationLoaded('atualizador');

        return $this->mergeWhen($carregada, fn () => [
            'criado_por'     => $this->criador ? ['id' => $this->criador->id, 'nome' => $this->criador->name] : null,
            'atualizado_por' => $this->atualizador ? ['id' => $this->atualizador->id, 'nome' => $this->atualizador->name] : null,
            'criado_em'      => $this->created_at,
            'atualizado_em'  => $this->updated_at,
        ]);
    }
}
