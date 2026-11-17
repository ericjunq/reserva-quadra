<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservaParceiroResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'data_reserva'         => $this->data_reserva->format('Y-m-d'),
            'horario'              => $this->horario?->value,
            'hora_inicio'          => substr($this->hora_inicio, 0, 5),
            'hora_fim'             => substr($this->hora_fim, 0, 5),
            'status'               => $this->status->value,
            'situacao_confirmacao' => $this->situacaoConfirmacao()?->value,
            'confirmar_ate'        => $this->confirmar_ate,
            'confirmada_em'        => $this->confirmada_em,
            'cancelada_em'         => $this->cancelada_em,
            'revogada_em'          => $this->revogada_em,
            'espaco'               => ['id' => $this->espaco->id, 'nome' => $this->espaco->nome],
            'equipamentos'         => $this->equipamentos->map(fn ($equipamento) => [
                'id'                 => $equipamento->id,
                'nome'               => $equipamento->nome,
                'quantidade'         => $equipamento->pivot->quantidade,
                'situacao_do_pedido' => $equipamento->pivot->situacao_solicitacao?->value,
            ])->values(),
            'criada_em' => $this->created_at,
        ];
    }
}
