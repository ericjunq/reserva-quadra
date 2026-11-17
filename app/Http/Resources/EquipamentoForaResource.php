<?php

namespace App\Http\Resources;

use App\Constants\TipoReservaConstant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipamentoForaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $reserva = $this->reserva;

        return [
            'equipamento' => ['id' => $this->equipamento->id, 'nome' => $this->equipamento->nome],
            'quantidade'  => $this->quantidade_retirada,
            'retirado_em' => $this->retirado_em,
            'reserva'     => [
                'id'           => $reserva->id,
                'data_reserva' => $reserva->data_reserva->format('Y-m-d'),
                'hora_inicio'  => substr($reserva->hora_inicio, 0, 5),
                'hora_fim'     => substr($reserva->hora_fim, 0, 5),
                'espaco'       => $reserva->espaco?->nome,
                'com_quem'     => $reserva->tipo === TipoReservaConstant::EVENTO
                    ? ($reserva->responsavel_nome ?? $reserva->descricao)
                    : $reserva->aluno?->nome_completo,
                'terminou' => $reserva->fim()->isPast(),
            ],
        ];
    }
}
