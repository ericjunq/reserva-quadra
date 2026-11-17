<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MovimentacaoEstoqueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'tipo'       => $this->tipo->value,
            'quantidade' => $this->quantidade,
            'estado'     => $this->estado,
            'reserva'    => $this->reserva ? [
                'id'           => $this->reserva->id,
                'tipo'         => $this->reserva->tipo->value,
                'data_reserva' => $this->reserva->data_reserva->toDateString(),
            ] : null,
            'feito_por'               => $this->usuario ? ['id' => $this->usuario->id, 'nome' => $this->usuario->name] : null,
            'quantidade_total_depois' => $this->quantidade_total_depois,
            'em_manutencao_depois'    => $this->em_manutencao_depois,
            'em'                      => $this->created_at,
        ];
    }
}
