<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => (int) $this->id,
            'data_reserva'         => (string) $this->data_reserva->format('Y-m-d'),
            'horario'              => $this->horario ? (string) $this->horario->value : null,
            'hora_inicio'          => substr($this->hora_inicio, 0, 5),
            'hora_fim'             => substr($this->hora_fim, 0, 5),
            'status'               => (string) $this->status->value,
            'situacao_confirmacao' => $this->situacaoConfirmacao() ? (string) $this->situacaoConfirmacao()->value : null,
            'confirmar_ate'        => $this->confirmar_ate ? (string) $this->confirmar_ate->toIso8601String() : null,
            'confirmada_em'        => $this->confirmada_em ? (string) $this->confirmada_em->toIso8601String() : null,
            'cancelada_em'         => $this->cancelada_em ? (string) $this->cancelada_em->toIso8601String() : null,
            'revogada_em'          => $this->revogada_em ? (string) $this->revogada_em->toIso8601String() : null,
            'espaco'               => ['id' => (int) $this->espaco->id, 'nome' => (string) $this->espaco->nome],
            'equipamentos'         => EquipamentoDaReservaResource::collection($this->equipamentos),
            'criada_em'            => (string) $this->created_at->toIso8601String(),
        ];
    }
}
