<?php

namespace App\Http\Resources;

use App\Constants\StatusConstant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->data_reserva->format('Y-m-d');
        $revogada = $this->status === StatusConstant::REVOGADO;

        return [
            'id'             => $this->id,
            'data_reserva'   => $data,
            'horario'        => $this->horario?->value,
            'hora_inicio'    => substr($this->hora_inicio, 0, 5),
            'hora_fim'       => substr($this->hora_fim, 0, 5),
            'status'         => $this->status->value,
            'tipo'           => $this->tipo->value,
            'espaco_id'      => $this->espaco_id,
            'aluno_id'       => $this->aluno_id,
            'descricao'      => $this->descricao,
            'equipamentos'   => $this->whenLoaded('equipamentos', fn () => $this->equipamentos->map(fn ($e) => [
                'id'         => $e->id,
                'nome'       => $e->nome,
                'quantidade' => $e->pivot->quantidade,
            ])),
            'justificativa_revogacao' => $this->when($revogada, $this->justificativa_revogacao),
            'revogada_em'    => $this->when($revogada, $this->revogada_em),
        ];
    }
}
